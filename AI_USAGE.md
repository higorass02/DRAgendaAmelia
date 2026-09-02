# Uso de IA neste projeto

Documento de transparência sobre o uso de assistentes de IA no desenvolvimento
deste projeto, conforme pedido pelo desafio.

## Ferramenta

**Claude Code** (Anthropic), operando como par de desenvolvimento dentro do
repositório, com acesso a shell, Docker e ao sistema de arquivos local.

## Como foi usado

- **Fase 0 — Ambiente e esqueleto:** geração do `docker-compose.yml`, dos
  Dockerfiles (`docker/api`, `docker/frontend`) e do `nginx.conf`; scaffolding
  do Laravel 13 (`backend/`) e do Vue 3 + Vite (`frontend/`); instalação e
  configuração de Sanctum, driver de fila RabbitMQ, Tailwind CSS e
  shadcn-vue; defaults de segurança (CORS allowlist, headers, rate limiters
  nomeados); verificação ponta a ponta (health checks, publish/consume real
  no RabbitMQ, build de produção do frontend).

- **Fase 1 — Modelagem de domínio:** as decisões de modelagem em aberto
  (disponibilidade do profissional, intervalo da consulta, quem se
  autentica, quais relatórios entram no escopo) foram discutidas e decididas
  em conversa **antes** de qualquer geração de schema — por instrução
  explícita do `CLAUDE.md`, a IA não modela domínio sozinha. Depois disso:
  migrations, models, enums (`AppointmentStatus`, `UserRole`), factories e
  seeder gerados e verificados (constraints testadas de verdade — CPF único,
  linhagem de remarcação — não só sintaticamente).

- **Fase 2 — Máquina de estados + regras de negócio:** desenvolvimento
  conduzido por TDD com PHPUnit — cada regra teve teste escrito e rodado
  **RED** (falhando) antes de qualquer código de implementação, depois
  **GREEN**. Ambiente de teste rodando contra MySQL real (banco dedicado
  `dragenda_testing`), não SQLite, porque o teste de conflito de agenda sob
  concorrência depende de locking real do InnoDB (`SELECT ... FOR UPDATE`).
  O teste de concorrência em si foi verificado com uma mutação deliberada
  (remover o lock, confirmar que o teste falha, restaurar) — prática de
  "testar o teste" pra não confiar cegamente numa suíte verde.

- **Fase 3 — API:** também por TDD (RED confirmado antes de cada
  controller/Policy existir). Adicionado Swagger/OpenAPI (`l5-swagger`) a
  pedido — documentação pública, autenticação Bearer declarada no schema.
  Um bug real foi encontrado só porque a verificação não parou nos testes
  automatizados: um `curl` manual contra o ambiente rodando (não só
  `getJson()`/`postJson()`, que mandam `Accept: application/json` e
  mascaravam o problema) expôs que requisições sem esse header recebiam 500
  em vez de 401. Corrigido e coberto com teste de regressão específico.

*(Seções seguintes serão preenchidas conforme o projeto avança pelas fases.)*

## O que foi revisado manualmente

Todo código gerado foi executado e verificado antes de ser considerado
pronto (containers sobem, migrations rodam, endpoints respondem, build
passa) — não apenas gerado e aceito às cegas. Decisões de modelagem de
domínio (Fase 1 em diante) são discutidas em conversa antes de qualquer
geração de código, por instrução explícita do `CLAUDE.md`.

## O que é humano vs. gerado

- **Decisões de arquitetura e trade-offs:** discutidos e aprovados por mim
  (autor humano) antes da implementação; registrados em `CLAUDE.md` e nos
  ADRs (`docs/adr/`).
- **Código de infraestrutura e boilerplate:** gerado com apoio da IA,
  revisado e testado antes do commit.
- **Regras de negócio críticas** (máquina de estados, conflito de agenda):
  *pendente — será documentado na Fase 2.*
