<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Observer genérico registrado nos modelos que só têm CRUD simples de
 * controller (Patient, Professional) — sem uma camada de Actions própria
 * onde chamar AuditLog::record() explicitamente faz mais sentido (caso de
 * Appointment, User, que logam explicitamente onde o ator já está à mão).
 */
class AuditObserver
{
    public function __construct(private readonly string $subjectType, private readonly string $labelAttribute = 'name')
    {
    }

    public function created(Model $model): void
    {
        AuditLog::record(
            actor: Auth::user(),
            action: 'created',
            subjectType: $this->subjectType,
            subjectId: $model->getKey(),
            subjectLabel: $model->{$this->labelAttribute} ?? null,
        );
    }

    public function updated(Model $model): void
    {
        // getOriginal() ainda reflete o valor de ANTES desse save() no
        // momento em que o evento "updated" dispara — o Model só chama
        // syncOriginal() depois, no fireModelEvent('saved').
        $changes = collect($model->getChanges())
            ->except(['updated_at'])
            ->mapWithKeys(fn ($value, $key) => [$key => [
                'from' => $model->getOriginal($key),
                'to' => $value,
            ]])
            ->all();

        if ($changes === []) {
            return;
        }

        AuditLog::record(
            actor: Auth::user(),
            action: 'updated',
            subjectType: $this->subjectType,
            subjectId: $model->getKey(),
            subjectLabel: $model->{$this->labelAttribute} ?? null,
            changes: $changes,
        );
    }
}
