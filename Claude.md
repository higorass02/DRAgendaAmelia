# CLAUDE.md — Contexto do Projeto

> Fonte de verdade para toda sessão do Claude Code neste repositório.
> Leia este arquivo inteiro antes de executar qualquer tarefa. Se algo aqui
> conflitar com um pedido pontual, sinalize o conflito antes de agir.

---

## 1. O que é este projeto

Módulo full stack de **agendamento de consultas** para uma healthtech (cenário de
processo seletivo). Permite cadastrar pacientes e profissionais de saúde, agendar
consultas e movimentar cada consulta pelos estados do seu ciclo de vida, com
trilha de auditoria, regras de negócio de saúde e conformidade com a LGPD.

O foco da avaliação **não** é quantidade de features — é **julgamento técnico** e
**clareza na comunicação das decisões**. Documentação (README, ADRs, AI_USAGE)
vale tanto quanto o código.

### Restrição de nomenclatura (obrigatória)
Não usar o nome da empresa do desafio em nomes de repositório, classes, funções ou
identificadores. Nome de trabalho do repo: `healthtech-scheduling` (ou similar
neutro). Inspiração visual é permitida; o nome, não.

---

## 2. Stack e decisões travadas

Estas decisões já foram tomadas e justificadas. Não trocar sem pedir.

| Camada             | Decisão                                                        |
|--------------------|----------------------------------------------------------------|
| Backend            | **Laravel 13** (stable atual), **PHP 8.3+**                    |
| Frontend           | **Vue 3** (Composition API) + **JavaScript**                   |
| Banco              | **MySQL 8** (InnoDB) + **phpMyAdmin**                          |
| Auth               | **Laravel Sanctum**, modo **token bearer**                     |
| Fila               | **RabbitMQ** (`vladimir-yuldashev/laravel-queue-rabbitmq`)     |
| Worker             | Container **dedicado** rodando `queue:work` (ambiente isolado) |
| Testes             | **PHPUnit**                                                    |
| Máquina de estados | **Hand-rolled** (explícita, sem pacote)                        |
| Ambiente           | **Docker Compose** — sobe tudo com um comando                  |
| Repositório        | **Monorepo** público (`/backend`, `/frontend`, `/docs`)        |

Notas de decisão (viram ADR depois):
- **Sanctum token vs cookie:** escolhido token bearer pela simplicidade de
  demo/teste. Registrar no README que em produção same-origin o modo cookie
  (SPA) seria o mais canônico.
- **RabbitMQ é mais que o mínimo** para o escopo (driver `database`/Redis
  bastaria). Justificar no ADR "Isolamento da notificação" pelo que ele
  entrega de verdade: durabilidade de mensagem, retry com backoff e
  **dead-letter queue** para notificação que falha — respondendo à pergunta de
  resiliência (seção 9 do desafio).

---

## 3. Características arquiteturais priorizadas (proposta — vetável)

1. **Confiabilidade** — o sistema nunca pode dar overbooking nem corromper o
   estado de uma consulta. Sustentada por: transações + lock no conflito de
   agenda, índice único como rede final, máquina de estados com transições
   válidas explícitas e estados terminais irreversíveis, fila durável com DLQ.
2. **Segurança** — dado de paciente é sensível (LGPD art. 5º, II). Sustentada
   por: Sanctum, Policies (controle de acesso / anti-IDOR), rate limit,
   minimização de PII via API Resources, CORS allowlist, headers de segurança.
3. **Manutenibilidade** — código que a equipe evolui com segurança. Sustentada
   por: domínio explícito, camada de ações/serviços, migrations versionadas,
   cobertura de testes nas regras críticas, ADRs.

Trade-off assumido: **não** estamos priorizando performance bruta/escala máxima.
A pergunta de "volume 50x" é tratada com índices, agregação e fila — "bom o
suficiente" — e discutida no README, não perseguida como prioridade nº 1.

---

## 4. Estrutura de pastas (alvo)

```
/
├── CLAUDE.md                 # este arquivo
├── README.md                 # como rodar, decisões, limitações, link do vídeo
├── AI_USAGE.md               # transparência no uso de IA 
├── docker-compose.yml
├── .env.example              # variáveis do ambiente compose
├── docker/
│   ├── api/Dockerfile        # PHP 8.3-fpm + extensões
│   ├── api/nginx.conf
│   └── frontend/Dockerfile
├── docs/
│   └── adr/                  # ADRs (Contexto, Decisão, Alternativas, Consequências)
├── backend/                  # Laravel 13
└── frontend/                 # Vue 3 + Vite (JS)
```

---

## 5. Convenções (vetáveis, mas aplicar de forma consistente)

- **Idioma no código:** identificadores em **inglês** (`Patient`, `Professional`,
  `Appointment`). Rótulos de UI e mensagens ao usuário em **PT-BR**.
- **Máquina de estados:** `enum` interno em inglês; método `label()` devolve o
  nome PT-BR para exibição. Desacopla representação interna de apresentação.
- **API:** REST, versionada em `/api/v1`. Respostas sempre via **API Resources**
  (nunca `return $model` cru — minimização de PII).
- **Validação:** sempre em **Form Requests**, nunca no controller.
- **Regras de negócio:** em **Actions/Services**, não em controllers nem models
  gordos. Controller orquestra; Action executa; Model representa.
- **Migrations:** versionadas, uma responsabilidade por migration.
- **Git:** commits pequenos e descritivos (Conventional Commits:
  `feat:`, `fix:`, `test:`, `docs:`, `chore:`).

---

## 6. Domínio (conceitual — schema detalhado é Fase 2)

Não gerar migrations/models nesta fase. Isto é o mapa conceitual para alinhar
depois em conversa. Pontos marcados com ⚠️ são decisões de modelagem em aberto.

### Entidades
- **Patient:** name, cpf, phone, email, birth_date.
- **Professional:** name, specialty, janela de disponibilidade (dias + horários).
  - ⚠️ Modelar disponibilidade como tabela própria (`weekday`, `start_time`,
    `end_time`) em vez de JSON — mais consultável. Decidir na Fase 1.
- **Appointment:** patient_id, professional_id, início da consulta, status atual.
  - ⚠️ Guardar `start_at` + `end_at` (ou `start_at` + `duration`) — precisamos
    de intervalo, não só "data e hora", para detectar sobreposição. Decidir na
    Fase 1. Isto amarra direto na estratégia de conflito de agenda.
  - Linhagem de remarcação: `rescheduled_from_id` / `rescheduled_to_id`.
  - Cancelamento: motivo + capacidade de distinguir antecedência.
- **StatusHistory:** appointment_id, from_status, to_status, reason, changed_by,
  changed_at. É a trilha de auditoria (seção 4.4).

### Máquina de estados (transições travadas pelo desafio)
```
SCHEDULED    (Agendada)        -> CONFIRMED, CANCELLED
CONFIRMED    (Confirmada)      -> IN_PROGRESS, RESCHEDULED, CANCELLED, NO_SHOW
IN_PROGRESS  (Em Atendimento)  -> COMPLETED
COMPLETED    (Realizada)       -> [terminal]
RESCHEDULED  (Remarcada)       -> [terminal] (gera nova Appointment vinculada)
CANCELLED    (Cancelada)       -> [terminal]
NO_SHOW      (Não Compareceu)  -> [terminal]
```
Estados terminais **não** voltam. Toda transição válida grava StatusHistory
dentro de uma transação.

### Regras de negócio (todas precisam estar pensadas; nem todas 100% implementadas)
- **Conflito de agenda sob concorrência:** um profissional não pode ter duas
  consultas confirmadas sobrepostas. Estratégia (MySQL): transação +
  `SELECT ... FOR UPDATE` + índice único como rede final. Detalhar em ADR.
- **Janela de disponibilidade:** consulta só dentro do horário do profissional.
- **Cancelamento com antecedência:** distinguir cancelamento com < X horas
  (definir e justificar X; sugestão inicial: 24h) dos demais. Sem cobrança de
  multa — só a distinção precisa existir.
- **Reagendamento preserva histórico:** remarcar não apaga o registro original;
  rastreabilidade antiga ↔ nova.
- **Dado sensível (LGPD):** controle de acesso + minimização na API.

---

## 7. Requisitos de segurança (aplicar sempre que couber)

Severidade "X de 5" = prioridade.

- **Rate limit (5/5):** limitar por **IP + por conta** nas rotas de auth; throttle
  mais brando em rotas de escrita com efeito colateral (criar/confirmar consulta).
- **CORS (4/5):** **allowlist** de origens em `config/cors.php`. Nunca refletir o
  `Origin` recebido.
- **Minimização de PII (4/5):** retornar só o necessário. Nunca vazar hash de
  senha ou campos sensíveis. Tudo via API Resources.
- **Token:** no **header** (bearer), nunca na URL. **Revogar no logout**
  (`currentAccessToken()->delete()`).
- **Anti-enumeração de usuário:** mensagem de erro de login **genérica** (não
  revelar se o e-mail existe).
- **Clickjacking:** header `X-Frame-Options` (+ CSP `frame-ancestors`) via
  middleware de headers de segurança.
- **SQL Injection (crítico):** só Eloquent/query builder com bindings. Nunca
  concatenar input em SQL. Se aparecer, é parada de linha.
- **IDOR (crítico):** **Policies** em todo acesso a recurso de paciente/consulta.
  Nunca confiar em ID vindo do cliente sem checar ownership/autorização. Amarra
  direto na exigência de LGPD. Se aparecer, é parada de linha.

---

## 8. Testes (PHPUnit)

Cobertura obrigatória mínima (exigência do desafio):
- **Conflito de agenda** — inclusive o cenário concorrente.
- **Transições de status** — válidas passam, inválidas são rejeitadas, terminais
  não voltam.

Além do mínimo: cancelamento com antecedência (distinção), linhagem de
remarcação, minimização/autorização nas respostas da API.

---

## 9. Área de relatórios (read-model sobre o domínio)

Relatórios reaproveitam auditoria + regras já existentes. Implementar 2–3 de
fato; o resto fica citado como roadmap. Candidatos (a confirmar na Fase 1):
- Taxa de **no-show**.
- **Cancelamentos por antecedência** (< X h vs. demais).
- Taxa de **remarcação**.
- **Ocupação** por profissional.
- Volume por período/status.

Gancho de escala: relatório é agregação → discutir índices, query e cache no
README (pergunta de "volume 50x").

---

## 10. Frontend e design (ferramentas — todas gratuitas)

- **Tailwind CSS** + **shadcn-vue** como base de componentes (grátis, bonito,
  customizável).
- Apoios durante o build de UI: skill **frontend-design** (tipografia, paleta,
  animação), **Web Design Guidelines da Vercel** (polir antes de entregar),
  **Chrome DevTools MCP** (abrir em navegador real e corrigir), **Magic MCP
  (21st.dev)** para add-ons/animações. Usar **apenas recursos gratuitos**.
- UX obrigatória do desafio: comunicar estados **sem jargão técnico**; diferenciar
  visualmente "Cancelada" de "Não Compareceu" (cor + ícone + rótulo, não só cor);
  interface responsiva; visão em **kanban/lista/agenda** com mudança de status.

---

## 11. Plano em fases

- **Fase 0 — Ambiente e esqueleto (ATUAL).** Docker Compose completo, Laravel 13
  instalado e configurado (MySQL, Sanctum, driver RabbitMQ), Vue 3 + Vite +
  Tailwind + shadcn-vue, defaults seguros (CORS allowlist, headers, config de
  rate limiter), rota de health check ponta a ponta, stubs de README/AI_USAGE/ADR.
  **Não** criar migrations/models de domínio ainda.
- **Fase 1 — Modelagem de domínio.** Fechar ⚠️ (intervalo da consulta,
  disponibilidade, relatórios) em conversa. Depois: schema + migrations + models
  + factories + seeders.
- **Fase 2 — Máquina de estados + regras de negócio.** Enum, mapa de transições,
  `transitionTo()`, histórico, conflito sob concorrência, janela, cancelamento,
  remarcação.
- **Fase 3 — API.** Rotas `/api/v1`, controllers finos, Form Requests, Resources,
  Policies, rate limit aplicado, auth Sanctum.
- **Fase 4 — Notificação desacoplada.** Evento de domínio → job enfileirado no
  RabbitMQ → worker. Resiliência + DLQ.
- **Fase 5 — Frontend.** Shell, auth, kanban, relatórios, tratamento de erro/UX.
- **Fase 6 — Testes + docs + vídeo.** Cobertura crítica, 3 ADRs, README completo,
  AI_USAGE, GIF/vídeo.

---

## 12. Regras de operação para o Claude Code

- Executar apenas o escopo da fase atual. Não adiantar fases futuras.
- Diante de decisão de modelagem em aberto (⚠️) ou ambiguidade, **parar e
  perguntar** — não inventar schema de domínio.
- Rodar e **verificar** o que construir (subir containers, rodar migrate/teste)
  antes de dar a fase por concluída.
- Manter este arquivo atualizado quando uma decisão nova for tomada.