<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Healthtech Scheduling API',
    description: 'Agendamento de consultas: pacientes, profissionais e ciclo de vida da consulta.'
)]
#[OA\Server(url: '/api/v1', description: 'Servidor atual')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum token',
    description: 'Token obtido em POST /auth/login. Enviar como "Authorization: Bearer {token}".'
)]
#[OA\Tag(name: 'Auth', description: 'Autenticação (Sanctum)')]
#[OA\Tag(name: 'Patients', description: 'Pacientes')]
#[OA\Tag(name: 'Professionals', description: 'Profissionais de saúde')]
#[OA\Tag(name: 'Appointments', description: 'Consultas e ciclo de vida')]
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
