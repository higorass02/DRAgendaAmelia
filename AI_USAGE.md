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
