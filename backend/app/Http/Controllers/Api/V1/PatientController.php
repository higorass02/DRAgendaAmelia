<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Patients\StorePatientRequest;
use App\Http\Requests\Patients\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Support\Http\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class PatientController extends Controller
{
    private const SORTABLE = [
        'name' => 'name',
        'cpf' => 'cpf',
        'email' => 'email',
        'birth_date' => 'birth_date',
        'created_at' => 'created_at',
    ];

    #[OA\Get(
        path: '/patients',
        tags: ['Patients'],
        summary: 'Lista pacientes (paginada, filtrável por nome, CPF, telefone, e-mail e data de nascimento)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Itens por página (máx. 100)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'cpf', 'email', 'birth_date', 'created_at'])),
            new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'cpf', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'phone', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'email', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'birth_date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'birth_date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de pacientes'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão (só staff)'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', '%'.$request->string('name').'%'))
            ->when($request->filled('cpf'), function ($q) use ($request) {
                $cpf = preg_replace('/\D/', '', (string) $request->string('cpf'));
                $q->where('cpf', 'like', "%{$cpf}%");
            })
            ->when($request->filled('phone'), fn ($q) => $q->where('phone', 'like', '%'.$request->string('phone').'%'))
            ->when($request->filled('email'), fn ($q) => $q->where('email', 'like', '%'.$request->string('email').'%'))
            ->when($request->filled('birth_date_from'), fn ($q) => $q->whereDate('birth_date', '>=', $request->date('birth_date_from')))
            ->when($request->filled('birth_date_to'), fn ($q) => $q->whereDate('birth_date', '<=', $request->date('birth_date_to')))
            ->tap(fn ($q) => ListQuery::applySort($q, $request, self::SORTABLE, 'name'))
            ->paginate(ListQuery::perPage($request));

        return PatientResource::collection($patients);
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
