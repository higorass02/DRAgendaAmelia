# ADR 0001 — Isolamento da notificação via RabbitMQ

Status: aceito

## Contexto

Quando uma consulta é agendada, confirmada ou cancelada, o paciente precisa
ser notificado. Essa notificação:

- **Não pode bloquear** a resposta HTTP da API (enviar e-mail é lento e pode
  falhar por motivos alheios ao domínio: provedor de e-mail fora do ar,
  timeout, etc.).
- **Não pode se perder** se falhar — nem silenciosamente, nem exigindo que
  alguém perceba manualmente.
- Precisa de **retry** (falha transitória não deveria virar falha
  permanente) e de um jeito de **inspecionar o que falhou de vez**.

O desafio pede explicitamente pra pensar em resiliência (seção 9): "o que
acontece se a notificação falhar?".

## Decisão

Notificação é 100% desacoplada da requisição HTTP via evento de domínio →
listener em fila (`ShouldQueue`) → RabbitMQ → worker dedicado:

```
Action (Schedule/Transition) 
  → event() [AppointmentScheduled | AppointmentStatusChanged]
    → Listener (ShouldQueue, queue "notifications", afterCommit=true)
      → RabbitMQ (fila "notifications")
        → worker (queue:work --tries=3 --backoff=5)
          → Notification::toMail() [driver "log" neste ambiente]
```

Pontos de design:

- **`afterCommit=true`** nos listeners: o job só é *de fato* publicado no
  RabbitMQ depois que a transação de banco que originou o evento commitar.
  Sem isso, uma operação que falha e sofre rollback (ex.: `RescheduleAppointment`
  cujo passo final falha) já teria enfileirado uma notificação para uma
  consulta que nunca existiu de verdade.
- **Fila dedicada `notifications`**: isola esse tipo de job de qualquer outro
  que o sistema venha a ter no futuro — cada fila tem sua própria política de
  retry/DLQ.
- **Retry com backoff**: `--tries=3 --backoff=5` no worker. Falha transitória
  (ex.: SMTP fora do ar por 10s) se resolve sozinha sem virar erro permanente.
- **Dead-letter queue**: o pacote `vladimir-yuldashev/laravel-queue-rabbitmq`
  rejeita a mensagem no RabbitMQ (`basic_reject`, sem requeue) quando o
  Laravel esgota as tentativas (`config/queue.php`, `reroute_failed => true`).
  Com isso, o RabbitMQ desvia a mensagem pra fila `notifications.failed` em
  vez de descartá-la — ela fica lá, inspecionável, em vez de desaparecer.
  Essa fila é declarada de antemão via `php artisan rabbitmq:provision`
  (comando idempotente, roda no boot do worker) porque, ao contrário da fila
  principal, nunca é publicada/consumida pelo código da aplicação — só
  recebe via dead-lettering do broker, então precisa existir de antemão ou a
  mensagem rejeitada é descartada silenciosamente.

## Por que RabbitMQ (não `database`/Redis)

`database` ou Redis resolveriam o "não bloquear a requisição". A escolha de
RabbitMQ é sobre o que ele entrega *a mais*, que endereça resiliência de
verdade: durabilidade de mensagem no broker (sobrevive a restart do worker
sem perder o que estava em trânsito), backoff nativo via fila de delay, e
dead-lettering nativo do protocolo AMQP — em vez de reimplementar essa lógica
em cima de uma tabela SQL.

## Evidência (verificação real, não só teste automatizado)

Durante o desenvolvimento, um bug real nos listeners (`$connection` do
RabbitMQ fixada na classe, ignorando o `QUEUE_CONNECTION=sync` do ambiente de
teste) vazou jobs reais pro broker referenciando registros que só existiam no
banco de testes. O worker do ambiente de dev, ao processá-los, falhava de
verdade (paciente não encontrado). Isso acabou virando uma prova de
resiliência não-planejada: as 14 mensagens realmente passaram por
`--tries=3 --backoff=5` e caíram em `notifications.failed`, com os headers
nativos do RabbitMQ confirmando a jornada:

```
x-first-death-reason: expired   (fila de delay do backoff)
x-last-death-reason:  rejected  (esgotou as tentativas)
```

Ou seja: a estratégia de resiliência foi validada contra uma falha real, não
só simulada. O bug foi corrigido (listener não fixa mais `$connection`, usa o
padrão do ambiente) e a fila foi purgada depois de capturar essa evidência.

## Alternativas consideradas

- **Sync (sem fila)** — mais simples, mas bloqueia a resposta HTTP e não tem
  retry nem DLQ. Rejeitado: viola o requisito de não travar a UX numa
  dependência externa lenta/instável.
- **`database` como driver de fila** — atende "não bloquear", mas retry e
  DLQ teriam que ser reimplementados manualmente (coluna de tentativas,
  tabela de "falhos", etc.) em vez de usar mecanismo nativo do broker.

## Consequências

- Um serviço a mais no `docker-compose.yml` (RabbitMQ + worker dedicado) —
  custo de operação justificado pelo que ele entrega, não só "porque pediram
  fila".
- Testes que disparam essas Actions **não podem** deixar o listener vazar pra
  fila real — `QUEUE_CONNECTION=sync` no `phpunit.xml` cobre isso, mas exige
  disciplina de não fixar `$connection` nas classes de listener (a lição
  vinda do bug acima).
- Mensagens na DLQ hoje só ficam paradas lá — não há um processo automático
  de replay/alerta. Fica como roadmap explícito (ver README/limitações).
