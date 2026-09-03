#!/usr/bin/env bash
#
# Setup automático do DRAgenda — sobe o ambiente Docker completo (MySQL,
# RabbitMQ, API, worker de fila, frontend, nginx), gera os arquivos de
# ambiente que faltarem, instala dependências, roda migrations/seed e
# confere que tudo respondeu no fim. Seguro rodar mais de uma vez: cada
# etapa só faz algo se ainda não tiver sido feito.
#
# Uso:
#   ./setup.sh
#
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

# --- saída colorida -----------------------------------------------------
if [ -t 1 ]; then
  BOLD='\033[1m'; GREEN='\033[1;32m'; YELLOW='\033[1;33m'; RED='\033[1;31m'; BLUE='\033[1;34m'; NC='\033[0m'
else
  BOLD=''; GREEN=''; YELLOW=''; RED=''; BLUE=''; NC=''
fi
step()  { echo -e "\n${BLUE}${BOLD}==> $1${NC}"; }
ok()    { echo -e "  ${GREEN}✓${NC} $1"; }
warn()  { echo -e "  ${YELLOW}⚠${NC}  $1"; REMINDERS+=("$1"); }
fail()  { echo -e "  ${RED}✗${NC} $1"; }
die()   { fail "$1"; echo -e "\n${RED}Setup interrompido.${NC}"; exit 1; }

REMINDERS=()

# --- pré-requisitos ------------------------------------------------------
step "Verificando pré-requisitos"
command -v docker >/dev/null 2>&1 || die "Docker não encontrado. Instale o Docker antes de continuar: https://docs.docker.com/get-docker/"
docker compose version >/dev/null 2>&1 || die "Docker Compose (plugin v2) não encontrado — 'docker compose version' falhou."
ok "Docker e Docker Compose disponíveis."

# --- .env da raiz (credenciais do docker-compose) ------------------------
step "Conferindo .env da raiz (senhas do MySQL/RabbitMQ, portas)"
if [ ! -f .env ]; then
  cp .env.example .env
  ok ".env criado a partir de .env.example."
  warn ".env foi criado agora com senhas de exemplo (change_me/change_me_root) — troque antes de usar isso fora da sua máquina."
else
  ok ".env já existe — não mexi nele."
fi

if grep -qE "^(DB_PASSWORD|DB_ROOT_PASSWORD|RABBITMQ_DEFAULT_PASS)=change_me" .env; then
  warn "Ainda há senha(s) 'change_me' em .env (DB_PASSWORD/DB_ROOT_PASSWORD/RABBITMQ_DEFAULT_PASS). Funciona localmente, mas edite antes de expor esse ambiente."
fi

# carrega as variáveis do .env da raiz pro shell deste script
set -a
# shellcheck disable=SC1091
source .env
set +a

: "${APP_PORT:=80}"
: "${FRONTEND_PORT:=5173}"
: "${PHPMYADMIN_PORT:=8080}"
: "${RABBITMQ_MANAGEMENT_PORT:=15672}"
: "${DB_DATABASE:=dragenda}"
: "${DB_USERNAME:=dragenda}"
: "${DB_PASSWORD:=change_me}"
: "${RABBITMQ_DEFAULT_USER:=dragenda}"
: "${RABBITMQ_DEFAULT_PASS:=change_me}"

# --- backend/.env (Laravel) — gerado a partir do template + segredos reais
step "Conferindo backend/.env (Laravel)"
if [ ! -f backend/.env ]; then
  cp backend/.env.example backend/.env
  # injeta as senhas de verdade (vindas do .env da raiz) no lugar dos placeholders
  sed -i.bak \
    -e "s/^DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE}/" \
    -e "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" \
    -e "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" \
    -e "s/^RABBITMQ_USER=.*/RABBITMQ_USER=${RABBITMQ_DEFAULT_USER}/" \
    -e "s/^RABBITMQ_PASSWORD=.*/RABBITMQ_PASSWORD=${RABBITMQ_DEFAULT_PASS}/" \
    -e "s#^APP_URL=.*#APP_URL=http://localhost:${APP_PORT}#" \
    -e "s#^CORS_ALLOWED_ORIGINS=.*#CORS_ALLOWED_ORIGINS=http://localhost:${FRONTEND_PORT},http://localhost:${APP_PORT}#" \
    backend/.env
  rm -f backend/.env.bak
  ok "backend/.env gerado a partir de backend/.env.example, com as credenciais de .env."
else
  ok "backend/.env já existe — não mexi nele."
  if grep -q "^DB_PASSWORD=change_me$" backend/.env; then
    warn "backend/.env tem DB_PASSWORD=change_me — se você trocou a senha em .env, atualize backend/.env manualmente também (ou apague o arquivo e rode ./setup.sh de novo)."
  fi
fi

# --- containers ------------------------------------------------------------
step "Subindo os containers (build + up -d)"
docker compose up -d --build || die "docker compose up falhou — veja a saída acima."
ok "Containers no ar."

wait_for_container() {
  local service="$1" tries=0 max=30
  step_msg="Aguardando o container '$service' ficar pronto"
  echo -e "  ${BLUE}…${NC} $step_msg"
  until docker compose exec -T "$service" true >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge "$max" ]; then
      die "'$service' não respondeu depois de ${max}x. Rode 'docker compose logs $service' pra investigar."
    fi
    sleep 2
  done
  ok "'$service' pronto."
}

wait_for_container api

# --- dependências PHP (vendor/ vive no bind mount do host) -----------------
step "Instalando dependências do backend (composer)"
if docker compose exec -T api test -f vendor/autoload.php >/dev/null 2>&1; then
  ok "vendor/ já existe — pulando composer install."
else
  docker compose exec -T api composer install --no-interaction --prefer-dist || die "composer install falhou."
  ok "composer install concluído."
fi

# --- APP_KEY -----------------------------------------------------------
step "Conferindo APP_KEY"
if grep -qE "^APP_KEY=base64:" backend/.env; then
  ok "APP_KEY já definida."
else
  docker compose exec -T api php artisan key:generate --force || die "php artisan key:generate falhou."
  ok "APP_KEY gerada."
fi

# --- migrations ----------------------------------------------------------
step "Rodando migrations"
docker compose exec -T api php artisan migrate --force || die "Migrations falharam — confira 'docker compose logs mysql' e as credenciais em backend/.env."
ok "Migrations em dia."

# --- seed (só se o banco ainda estiver vazio) -----------------------------
step "Conferindo dados de exemplo (seed)"
USER_COUNT=$(docker compose exec -T api php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -n1 | tr -d '\r')
if [ "${USER_COUNT:-0}" = "0" ]; then
  docker compose exec -T api php artisan db:seed --force || die "db:seed falhou."
  ok "Banco populado com dados de exemplo (pacientes, profissionais, consultas, usuários demo)."
else
  ok "Banco já tem dados (${USER_COUNT} usuário(s)) — pulando seed. Rode manualmente 'docker compose exec api php artisan migrate:fresh --seed' se quiser recomeçar do zero."
fi

# --- fila RabbitMQ (o worker já roda isso sozinho, mas confirma aqui) ------
step "Conferindo fila do RabbitMQ"
docker compose exec -T api php artisan rabbitmq:provision >/dev/null 2>&1 \
  && ok "Fila 'notifications' (+ DLQ) provisionada." \
  || warn "Não consegui provisionar a fila do RabbitMQ agora — o worker tenta de novo sozinho ao subir (docker compose logs worker)."

# --- frontend --------------------------------------------------------------
step "Conferindo dependências do frontend"
if docker compose exec -T frontend test -f node_modules/.bin/vite >/dev/null 2>&1; then
  ok "node_modules do frontend já está pronto."
else
  docker compose exec -T frontend npm install || die "npm install do frontend falhou."
  ok "Dependências do frontend instaladas."
fi

# --- health check ---------------------------------------------------------
step "Verificando se a aplicação está respondendo"
check_url() {
  local url="$1" label="$2" tries=0 max=15
  until curl -fsS -o /dev/null "$url" 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge "$max" ]; then
      warn "$label não respondeu em http://localhost:${APP_PORT} depois de ${max} tentativas — pode ainda estar subindo, confira 'docker compose logs'."
      return 1
    fi
    sleep 2
  done
  ok "$label respondendo."
}
check_url "http://localhost:${APP_PORT}/api/v1/health" "API"
check_url "http://localhost:${APP_PORT}/" "Frontend (via nginx)"

# --- resumo final ----------------------------------------------------------
step "Pronto"
cat <<EOF

  Aplicação            http://localhost:${APP_PORT}
  API                  http://localhost:${APP_PORT}/api/v1
  Documentação (Swagger) http://localhost:${APP_PORT}/api/documentation
  phpMyAdmin           http://localhost:${PHPMYADMIN_PORT}
  RabbitMQ management  http://localhost:${RABBITMQ_MANAGEMENT_PORT}

  Contas de demonstração (senha "password" para todas):
    admin@dragenda.test    (administrador — gerencia usuários e vê a auditoria)
    staff@dragenda.test    (equipe)
    patient@dragenda.test  (paciente)

EOF

if [ "${#REMINDERS[@]}" -gt 0 ]; then
  echo -e "${YELLOW}${BOLD}Coisas pra você revisar:${NC}"
  for r in "${REMINDERS[@]}"; do
    echo -e "  ${YELLOW}-${NC} $r"
  done
  echo
fi

echo "Comandos úteis:"
echo "  docker compose logs -f api          # logs do backend"
echo "  docker compose logs -f worker       # logs do worker de fila"
echo "  docker compose exec api php artisan test   # suíte de testes do backend"
echo "  docker compose down                 # derruba tudo"
