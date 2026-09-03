<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AuditLog',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'action', type: 'string', example: 'updated'),
        new OA\Property(property: 'action_label', type: 'string', example: 'Atualizado'),
        new OA\Property(property: 'subject_type', type: 'string', nullable: true, example: 'patient'),
        new OA\Property(property: 'subject_type_label', type: 'string', nullable: true, example: 'Paciente'),
        new OA\Property(property: 'subject_id', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'subject_label', type: 'string', nullable: true, example: 'Maria Silva'),
        new OA\Property(property: 'changes', type: 'object', nullable: true),
        new OA\Property(property: 'ip_address', type: 'string', nullable: true, example: '127.0.0.1'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class AuditLogResource extends JsonResource
{
    private const ACTION_LABELS = [
        'login' => 'Login',
        'logout' => 'Logout',
        'created' => 'Criado',
        'updated' => 'Atualizado',
        'deleted' => 'Excluído',
        'password_changed' => 'Senha alterada',
        'account_deleted' => 'Conta excluída (autoatendimento)',
        'scheduled' => 'Agendada',
        'confirmed' => 'Confirmada',
        'in_progress' => 'Iniciada',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
        'no_show' => 'Não compareceu',
        'rescheduled' => 'Remarcada',
    ];

    private const SUBJECT_TYPE_LABELS = [
        'patient' => 'Paciente',
        'professional' => 'Profissional',
        'appointment' => 'Consulta',
        'user' => 'Usuário',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->actor_id ? ['id' => $this->actor_id, 'name' => $this->actor_name] : null,
            'action' => $this->action,
            'action_label' => self::ACTION_LABELS[$this->action] ?? $this->action,
            'subject_type' => $this->subject_type,
            'subject_type_label' => self::SUBJECT_TYPE_LABELS[$this->subject_type] ?? $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_label' => $this->subject_label,
            'changes' => $this->changes,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
