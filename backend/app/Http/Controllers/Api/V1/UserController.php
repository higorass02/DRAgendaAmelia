<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Http\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * CRUD de usuários exclusivo de admin (App\Policies\UserPolicy) — ver
 * CLAUDE.md / pedido do usuário: "sistema de ROLE onde o admin tenha
 * poderes de gerenciar outros usuários".
 */
class UserController extends Controller
{
    private const SORTABLE = [
        'name' => 'name',
        'email' => 'email',
        'role' => 'role',
        'created_at' => 'created_at',
    ];

    #[OA\Get(
        path: '/users',
        tags: ['Users'],
        summary: 'Lista usuários (admin) — paginada, filtrável por nome, e-mail e papel',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'email', 'role', 'created_at'])),
            new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'email', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de usuários'),
            new OA\Response(response: 403, description: 'Sem permissão (só admin)'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', '%'.$request->string('name').'%'))
            ->when($request->filled('email'), fn ($q) => $q->where('email', 'like', '%'.$request->string('email').'%'))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->tap(fn ($q) => ListQuery::applySort($q, $request, self::SORTABLE, 'name'))
            ->paginate(ListQuery::perPage($request));

        return UserResource::collection($users);
    }

    #[OA\Post(
        path: '/users',
        tags: ['Users'],
        summary: 'Cria um usuário (admin)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'staff', 'patient']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuário criado'),
            new OA\Response(response: 403, description: 'Sem permissão (só admin)'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);
        $user->forceFill(['role' => $request->validated('role')])->save();

        AuditLog::record(
            actor: $request->user(),
            action: 'created',
            subjectType: 'user',
            subjectId: $user->id,
            subjectLabel: $user->name,
            changes: ['role' => $user->role->value],
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/users/{user}',
        tags: ['Users'],
        summary: 'Atualiza um usuário, incluindo o papel (admin)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Usuário atualizado'),
            new OA\Response(response: 403, description: 'Sem permissão (só admin)'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $before = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value];

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ]);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->validated('password'));
        }

        $user->save();
        $user->forceFill(['role' => $request->validated('role')])->save();

        $after = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value];
        $changes = collect($after)
            ->filter(fn ($value, $key) => $value !== $before[$key])
            ->mapWithKeys(fn ($value, $key) => [$key => ['from' => $before[$key], 'to' => $value]])
            ->all();

        AuditLog::record(
            actor: $request->user(),
            action: 'updated',
            subjectType: 'user',
            subjectId: $user->id,
            subjectLabel: $user->name,
            changes: $changes,
        );

        return new UserResource($user);
    }

    #[OA\Delete(
        path: '/users/{user}',
        tags: ['Users'],
        summary: 'Exclui um usuário (admin)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Usuário excluído'),
            new OA\Response(response: 403, description: 'Sem permissão (só admin)'),
            new OA\Response(response: 422, description: 'Não é possível excluir a própria conta por aqui'),
        ]
    )]
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'id' => 'Não é possível excluir a própria conta por aqui — use "Excluir minha conta" no seu perfil.',
            ]);
        }

        AuditLog::record(
            actor: $request->user(),
            action: 'deleted',
            subjectType: 'user',
            subjectId: $user->id,
            subjectLabel: $user->name,
        );

        $user->tokens()->delete();
        $user->delete();

        return response()->json(null, 204);
    }
}
