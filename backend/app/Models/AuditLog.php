<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-model de auditoria: "quem fez o quê, quando" — cobre CRUD de
 * pacientes/profissionais/usuários e o ciclo de vida de consultas (que já
 * tinha StatusHistory; aqui só é espelhado como mais uma entrada, pra dar
 * uma visão única entre entidades diferentes). Imutável por design: não tem
 * updated_at, e não existe rota de update/delete pra este recurso.
 */
#[Fillable(['actor_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes', 'ip_address'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(
        ?User $actor,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectLabel = null,
        array $changes = [],
    ): self {
        return self::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
