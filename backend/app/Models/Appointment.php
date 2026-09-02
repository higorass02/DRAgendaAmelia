<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'patient_id',
    'professional_id',
    'start_at',
    'end_at',
    'status',
    'rescheduled_from_id',
    'rescheduled_to_id',
    'created_by',
])]
class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'rescheduled_from_id');
    }

    public function rescheduledTo(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'rescheduled_to_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }
}
