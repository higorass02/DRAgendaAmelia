# DR Agenda Amélia

Módulo de agendamento de consultas: cadastro de pacientes e profissionais de
saúde, agendamento de consultas e ciclo de vida da consulta com trilha de
auditoria, regras de negócio de saúde e atenção à LGPD.

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
./setup.sh
```

Isso sobe tudo (containers, `.env`s, `composer install`/`npm install`,
migrations, seed de dados de exemplo) de forma automática e idempotente —
seguro rodar de novo a qualquer momento; ele avisa no final se sobrou algum
campo com valor de exemplo (`change_me`) pra revisar. Se preferir os passos
manuais:

```bash
cp .env.example .env                    # senhas do MySQL/RabbitMQ
cp backend/.env.example backend/.env    # copiar as mesmas senhas pra cá também
docker compose up -d --build
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
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

### Contas de demonstração

Criadas pelo seed (`php artisan db:seed`), senha `password` para todas:

| E-mail                  | Papel          |
|--------------------------|----------------|
| `admin@dragenda.test`    | Administrador — gerencia usuários e vê a trilha de auditoria |
| `staff@dragenda.test`    | Equipe         |
| `patient@dragenda.test`  | Paciente       |

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
