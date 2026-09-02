<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Professionals\StoreProfessionalRequest;
use App\Http\Requests\Professionals\UpdateProfessionalRequest;
use App\Http\Resources\ProfessionalResource;
use App\Models\Professional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ProfessionalController extends Controller
{
    #[OA\Get(
        path: '/professionals',
        tags: ['Professionals'],
        summary: 'Lista profissionais',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de profissionais')]
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Professional::class);

        return ProfessionalResource::collection(
            Professional::with('availabilities')->orderBy('name')->paginate(15)
        );
    }

    #[OA\Post(
        path: '/professionals',
        tags: ['Professionals'],
        summary: 'Cadastra um profissional com a janela de disponibilidade',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'specialty', 'availabilities'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Dr. João'),
                    new OA\Property(property: 'specialty', type: 'string', example: 'Cardiologia'),
                    new OA\Property(
                        property: 'availabilities',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'weekday', type: 'integer', example: 1),
                            new OA\Property(property: 'start_time', type: 'string', example: '08:00'),
                            new OA\Property(property: 'end_time', type: 'string', example: '18:00'),
                        ])
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Profissional criado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StoreProfessionalRequest $request): JsonResponse
    {
        $professional = DB::transaction(function () use ($request) {
            $professional = Professional::create($request->safe()->only(['name', 'specialty']));
            $professional->availabilities()->createMany($request->validated('availabilities'));

            return $professional;
        });

        return (new ProfessionalResource($professional->load('availabilities')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/professionals/{professional}',
        tags: ['Professionals'],
        summary: 'Detalhe de um profissional',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'professional', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Profissional')]
    )]
    public function show(Professional $professional): ProfessionalResource
    {
        $this->authorize('view', $professional);

        return new ProfessionalResource($professional->load('availabilities'));
    }

    #[OA\Put(
        path: '/professionals/{professional}',
        tags: ['Professionals'],
        summary: 'Atualiza um profissional (substitui a janela de disponibilidade)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'professional', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Profissional atualizado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdateProfessionalRequest $request, Professional $professional): ProfessionalResource
    {
        DB::transaction(function () use ($request, $professional) {
            $professional->update($request->safe()->only(['name', 'specialty']));
            $professional->availabilities()->delete();
            $professional->availabilities()->createMany($request->validated('availabilities'));
        });

        return new ProfessionalResource($professional->load('availabilities'));
    }
}
