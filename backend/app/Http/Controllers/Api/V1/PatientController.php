<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Patients\StorePatientRequest;
use App\Http\Requests\Patients\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class PatientController extends Controller
{
    #[OA\Get(
        path: '/patients',
        tags: ['Patients'],
        summary: 'Lista pacientes',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de pacientes'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão (só staff)'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Patient::class);

        return PatientResource::collection(Patient::orderBy('name')->paginate(15));
    }

    #[OA\Post(
        path: '/patients',
        tags: ['Patients'],
        summary: 'Cadastra um paciente',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'cpf', 'phone', 'birth_date'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Maria Silva'),
                    new OA\Property(property: 'cpf', type: 'string', example: '111.444.777-35'),
                    new OA\Property(property: 'phone', type: 'string', example: '11999998888'),
                    new OA\Property(property: 'email', type: 'string', nullable: true, example: 'maria@example.com'),
                    new OA\Property(property: 'birth_date', type: 'string', format: 'date', example: '1990-05-10'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Paciente criado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Patient'),
            ])),
            new OA\Response(response: 422, description: 'Erro de validação (CPF inválido/duplicado, etc.)'),
        ]
    )]
    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = Patient::create($request->validated());

        return (new PatientResource($patient))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/patients/{patient}',
        tags: ['Patients'],
        summary: 'Detalhe de um paciente',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'patient', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Paciente'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function show(Patient $patient): PatientResource
    {
        $this->authorize('view', $patient);

        return new PatientResource($patient);
    }

    #[OA\Put(
        path: '/patients/{patient}',
        tags: ['Patients'],
        summary: 'Atualiza um paciente',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'patient', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Paciente atualizado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        $patient->update($request->validated());

        return new PatientResource($patient);
    }
}
