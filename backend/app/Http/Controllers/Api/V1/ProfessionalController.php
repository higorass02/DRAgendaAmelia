<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Professionals\AvailableSlotsRequest;
use App\Http\Requests\Professionals\StoreProfessionalRequest;
use App\Http\Requests\Professionals\UpdateProfessionalRequest;
use App\Http\Resources\ProfessionalResource;
use App\Models\Appointment;
use App\Models\Professional;
use App\Support\Http\ListQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ProfessionalController extends Controller
{
    private const SORTABLE = [
        'name' => 'name',
        'specialty' => 'specialty',
        'created_at' => 'created_at',
    ];

    #[OA\Get(
        path: '/professionals',
        tags: ['Professionals'],
        summary: 'Lista profissionais (paginada, filtrável por nome e especialidade)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Itens por página (máx. 100)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'specialty', 'created_at'])),
            new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'specialty', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de profissionais')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Professional::class);

        $professionals = Professional::query()
            ->with('availabilities')
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', '%'.$request->string('name').'%'))
            ->when($request->filled('specialty'), fn ($q) => $q->where('specialty', 'like', '%'.$request->string('specialty').'%'))
            ->tap(fn ($q) => ListQuery::applySort($q, $request, self::SORTABLE, 'name'))
            ->paginate(ListQuery::perPage($request));

        return ProfessionalResource::collection($professionals);
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

    /**
     * Status que "ocupam" o horário do profissional — mesmo critério de
     * conflito usado em ScheduleAppointment::OCCUPYING_STATUSES.
     */
    private const OCCUPYING_STATUSES = [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed];

    #[OA\Get(
        path: '/professionals/{professional}/available-slots',
        tags: ['Professionals'],
        summary: 'Horários livres de um profissional num dia, dado a disponibilidade configurada e as consultas já marcadas',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'professional', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'duration_minutes', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'exclude_appointment_id',
                in: 'query',
                description: 'Ignora essa consulta ao calcular conflito (uso: remarcação)',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de horários HH:mm livres nesse dia'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function availableSlots(AvailableSlotsRequest $request, Professional $professional): JsonResponse
    {
        $this->authorize('view', $professional);

        $date = Carbon::parse($request->validated('date'))->startOfDay();
        $duration = $request->integer('duration_minutes') ?: 30;
        $excludeId = $request->integer('exclude_appointment_id') ?: null;

        $windows = $professional->availabilities()
            ->where('weekday', $date->dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $busy = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->whereDate('start_at', $date->toDateString())
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get(['start_at', 'end_at']);

        $slots = [];

        foreach ($windows as $window) {
            $slotStart = $date->copy()->setTimeFromTimeString($window->start_time);
            $windowEnd = $date->copy()->setTimeFromTimeString($window->end_time);

            while ($slotStart->copy()->addMinutes($duration)->lte($windowEnd)) {
                $slotEnd = $slotStart->copy()->addMinutes($duration);

                $conflict = $busy->contains(
                    fn ($a) => $slotStart->lt($a->end_at) && $slotEnd->gt($a->start_at)
                );

                if (! $conflict) {
                    $slots[] = $slotStart->format('H:i');
                }

                $slotStart->addMinutes($duration);
            }
        }

        return response()->json(['slots' => $slots]);
    }
}
