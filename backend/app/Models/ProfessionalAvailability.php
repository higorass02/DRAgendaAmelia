<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['professional_id', 'weekday', 'start_time', 'end_time'])]
class ProfessionalAvailability extends Model
{
    /** @use HasFactory<\Database\Factories\ProfessionalAvailabilityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
