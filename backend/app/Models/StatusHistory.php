<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'from_status', 'to_status', 'reason', 'changed_by', 'changed_at'])]
class StatusHistory extends Model
{
    /** @use HasFactory<\Database\Factories\StatusHistoryFactory> */
    use HasFactory;

    // Trilha de auditoria imutável: sem updated_at, changed_at é a fonte de verdade do "quando".
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
