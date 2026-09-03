<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

/**
 * Ações que o usuário autenticado faz sobre a própria conta — separado do
 * UserController (que é o CRUD de admin sobre OUTROS usuários) porque as
 * regras de autorização são diferentes: aqui basta estar autenticado, lá é
 * preciso ser admin (App\Policies\UserPolicy).
 */
class AccountController extends Controller
{
    #[OA\Put(
        path: '/me/password',
        tags: ['Account'],
        summary: 'Troca a própria senha',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Senha alterada'),
            new OA\Response(response: 422, description: 'Senha atual incorreta ou nova senha inválida'),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): UserResource
    {
        $user = $request->user();
        $user->update(['password' => Hash::make($request->validated('password'))]);

        return new UserResource($user);
    }

    #[OA\Delete(
        path: '/me',
        tags: ['Account'],
        summary: 'Exclui a própria conta',
        description: 'Exige a senha atual para confirmar. Revoga todos os tokens do usuário.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['password'], properties: [
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ])
        ),
        responses: [
            new OA\Response(response: 204, description: 'Conta excluída'),
            new OA\Response(response: 422, description: 'Senha incorreta'),
        ]
    )]
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(null, 204);
    }
}
