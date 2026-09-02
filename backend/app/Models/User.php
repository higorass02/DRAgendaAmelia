<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// "role" fica fora do Fillable de propósito: evita escalonamento de
// privilégio via mass assignment em endpoints de cadastro/registro.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * "role" fica fora do Fillable — sem isso, um User novo em memória (ex.:
     * logo após um create()) fica com o atributo ausente até um reload do
     * banco, porque o default da coluna só existe a nível de SQL. Sem este
     * default aqui, checagens de autorização feitas no mesmo request em que
     * o usuário foi criado veriam role null em vez de "staff".
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'staff',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }
}
