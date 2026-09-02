# Healthtech Scheduling

Módulo de agendamento de consultas: cadastro de pacientes e profissionais de
saúde, agendamento de consultas e ciclo de vida da consulta com trilha de
auditoria, regras de negócio de saúde e atenção à LGPD.

> Este README cobre o estado atual (Fase 0 — ambiente e esqueleto). Seções
> marcadas como pendentes serão preenchidas nas fases seguintes.

## Stack

| Camada             | Tecnologia                                              |
|---------------------|----------------------------------------------------------|
| Backend             | Laravel 13, PHP 8.3                                      |
| Frontend            | Vue 3 (Composition API) + Vite + Tailwind CSS + shadcn-vue |
| Banco               | MySQL 8 + phpMyAdmin                                      |
| Auth                | Laravel Sanctum (token bearer)                            |
| Fila                | RabbitMQ (`vladimir-yuldashev/laravel-queue-rabbitmq`)     |
| Ambiente            | Docker Compose                                             |

## Como rodar

```bash
cp .env.example .env
docker compose up -d --build
```

Isso sobe: MySQL, phpMyAdmin, RabbitMQ, API (Laravel + nginx), worker de fila
e frontend (Vite dev server). Tudo fica disponível em um único host:

| Serviço              | URL                          |
|------------------------|-------------------------------|
| Aplicação (frontend)   | http://localhost              |
| API                    | http://localhost/api/v1       |
| Documentação da API (Swagger) | http://localhost/api/documentation |
| Health check           | http://localhost/api/v1/health, http://localhost/up |
| phpMyAdmin             | http://localhost:8080         |
| RabbitMQ management    | http://localhost:15672        |

O nginx roteia `/api/*` e `/up` para o Laravel; o restante é proxy reverso
para o Vite dev server (com suporte a hot-reload).

### Comandos úteis

```bash
docker compose logs -f api        # logs do backend
docker compose logs -f worker     # logs do worker de fila
docker compose exec api php artisan migrate
docker compose exec frontend yarn watch   # já é o comando padrão do container
docker compose exec api php artisan rabbitmq:provision  # declara a fila notifications.failed (DLQ) — já roda automaticamente no boot do worker
```

Notificações (agendamento, confirmação, cancelamento) são assíncronas via
RabbitMQ (fila `notifications`), com retry e dead-letter queue — ver
[ADR 0001](./docs/adr/0001-isolamento-da-notificacao.md).

## Decisões de arquitetura

Justificativas detalhadas ficam em `docs/adr/`. Resumo das decisões travadas
e por quê está em [CLAUDE.md](./CLAUDE.md).

## Limitações conhecidas

*Pendente — será preenchido ao longo das fases seguintes.*

## Vídeo de demonstração

*Pendente — Fase 6.*
