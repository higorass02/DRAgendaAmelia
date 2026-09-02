<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class ProvisionRabbitMqQueues extends Command
{
    protected $signature = 'rabbitmq:provision';

    protected $description = 'Declara filas que não são criadas automaticamente pelo fluxo normal de publish/consume (ex.: DLQ de notificações).';

    public function handle(): int
    {
        /** @var RabbitMQQueue $connection */
        $connection = Queue::connection('rabbitmq');

        // "notifications" (fila principal) é declarada automaticamente pelo
        // pacote quando um job é publicado/consumido. "notifications.failed"
        // nunca é publicada/consumida por código nosso — só recebe mensagens
        // via dead-lettering do RabbitMQ — então precisa existir de antemão,
        // senão a mensagem rejeitada é descartada silenciosamente.
        $connection->declareQueue('notifications.failed', durable: true);

        $this->info('Fila notifications.failed declarada (idempotente).');

        return self::SUCCESS;
    }
}
