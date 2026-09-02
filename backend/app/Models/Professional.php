<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'specialty'])]
class Professional extends Model
{
    /** @use HasFactory<\Database\Factories\ProfessionalFactory> */
    use HasFactory;

    public function availabilities(): HasMany
    {
        return $this->hasMany(ProfessionalAvailability::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
