<?php

namespace App\Listeners\Notifications;

use App\Events\AppointmentScheduled;
use App\Notifications\AppointmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAppointmentScheduledNotification implements ShouldQueue
{
    // Sem $connection fixo de propósito: usa o QUEUE_CONNECTION do ambiente
    // (rabbitmq em dev/prod, sync nos testes). Fixar 'rabbitmq' aqui
    // ignoraria o override de teste e vazaria jobs reais pro broker.
    public string $queue = 'notifications';

    // Só efetiva o dispatch pro RabbitMQ depois que a transação que criou a
    // consulta commitar — senão, uma remarcação que falhar mais adiante (e
    // fizer rollback) já teria enfileirado uma notificação de uma consulta
    // que nunca chegou a existir de verdade.
    public bool $afterCommit = true;

    public function handle(AppointmentScheduled $event): void
    {
        $patient = $event->appointment->patient;

        if (! $patient->email) {
            return;
        }

        $patient->notify(new AppointmentNotification('scheduled', $event->appointment));
    }
}
