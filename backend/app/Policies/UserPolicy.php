<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gerenciamento de OUTROS usuários (papel/role, criação, exclusão) é
 * exclusivo de admin — staff continua sem acesso a essa tela. Ações sobre a
 * própria conta (trocar senha, se excluir) não passam por aqui, ficam nas
 * rotas /me (qualquer usuário autenticado pode agir sobre si mesmo).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
