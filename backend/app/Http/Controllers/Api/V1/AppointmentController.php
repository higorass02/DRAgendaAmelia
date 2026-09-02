<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Actions\Appointments\ScheduleAppointment;
use App\Actions\Appointments\TransitionAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Http\Requests\Appointments\CancelAppointmentRequest;
use App\Http\Requests\Appointments\RescheduleAppointmentRequest;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AppointmentController extends Controller
{
    private const WITH = ['patient', 'professional', 'createdBy'];

    #[OA\Get(
        path: '/appointments',
        tags: ['Appointments'],
        summary: 'Lista consultas (filtrável por status, profissional, paciente e período)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'professional_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de consultas')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Appointment::class);

        $appointments = Appointment::query()
            ->with(self::WITH)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('professional_id'), fn ($q) => $q->where('professional_id', $request->integer('professional_id')))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('start_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('start_at', '<=', $request->date('to')))
            ->orderBy('start_at')
            ->paginate(20);

        return AppointmentResource::collection($appointments);
    }

    #[OA\Post(
        path: '/appointments',
        tags: ['Appointments'],
        summary: 'Agenda uma nova consulta',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['patient_id', 'professional_id', 'start_at', 'end_at'],
                properties: [
                    new OA\Property(property: 'patient_id', type: 'integer'),
                    new OA\Property(property: 'professional_id', type: 'integer'),
                    new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Consulta agendada'),
            new OA\Response(response: 422, description: 'Validação ou fora da janela de disponibilidade'),
            new OA\Response(response: 409, description: 'Conflito de agenda'),
        ]
    )]
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = (new ScheduleAppointment)->handle(
            patient: Patient::findOrFail($request->integer('patient_id')),
            professional: Professional::findOrFail($request->integer('professional_id')),
            startAt: $request->date('start_at'),
            endAt: $request->date('end_at'),
            actor: $request->user(),
        );

        return (new AppointmentResource($appointment->load(self::WITH)))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/appointments/{appointment}',
        tags: ['Appointments'],
        summary: 'Detalhe de uma consulta',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'appointment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Consulta')]
    )]
    public function show(Appointment $appointment): AppointmentResource
    {
        $this->authorize('view', $appointment);

        return new AppointmentResource($appointment->load(self::WITH));
    }

    #[OA\Post(
        path: '/appointments/{appointment}/confirm',
        tags: ['Appointments'],
        summary: 'Confirma uma consulta agendada',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'appointment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Consulta confirmada'),
            new OA\Response(response: 422, description: 'Transição inválida'),
        ]
    )]
    public function confirm(Request $request, Appointment $appointment): AppointmentResource
    {
        return $this->transition($request, $appointment, AppointmentStatus::Confirmed);
    }

    #[OA\Post(
        path: '/appointments/{appointment}/start',
        tags: ['Appointments'],
        summary: 'Inicia o atendimento (Confirmada -> Em Atendimento)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'appointment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Atendimento iniciado')]
    )]
    public function start(Request $request, Appointment $appointment): AppointmentResource
    {
        return $this->transition($request, $appointment, AppointmentStatus::InProgress);
    }

    #[OA\Post(
        path: '/appointments/{appointment}/complete',
        tags: ['Appointments'],
        summary: 'Conclui o atendimento',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'appointment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Consulta concluída')]
    )]
    public function complete(Request $request, Appointment $appointment): AppointmentResource
    {
        return $this->transition($request, $appointment, AppointmentStatus::Completed);
    }

    #[OA\Post(
        path: '/appointments/{appointment}/no-show',
        tags: ['Appointments'],
        summary: 'Marca não comparecimento',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'appointment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Marcado como não comparecido')]
    )]
    public function noShow(Request $request, Appointment $appointment): AppointmentResource
    {
        return $this->transition($request, $appointment, AppointmentStatus::NoShow);
    }

    #[OA\Post(
        path: '/appointments/{appointment}/cancel',
        tags: ['Appointments'],
        summary: 'Cancela uma consulta',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'appointment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'reason', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 200, description: 'Consulta cancelada')]
    )]
    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): AppointmentResource
    {
        $updated = (new CancelAppointment)->handle($appointment, $request->user(), $request->validated('reason'));

        return new AppointmentResource($updated->load(self::WITH));
    }

    #[OA\Post(
        path: '/appointments/{appointment}/reschedule',
        tags: ['Appointments'],
        summary: 'Remarca uma consulta confirmada (cria uma nova vinculada)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'appointment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['start_at', 'end_at'],
            properties: [
                new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'reason', type: 'string', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Nova consulta criada, original marcada como remarcada'),
            new OA\Response(response: 422, description: 'Transição inválida ou fora da disponibilidade'),
            new OA\Response(response: 409, description: 'Conflito no novo horário'),
        ]
    )]
    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $new = (new RescheduleAppointment)->handle(
            original: $appointment,
            newStartAt: $request->date('start_at'),
            newEndAt: $request->date('end_at'),
            actor: $request->user(),
            reason: $request->validated('reason'),
        );

        return (new AppointmentResource($new->load(self::WITH)))->response()->setStatusCode(201);
    }

    private function transition(Request $request, Appointment $appointment, AppointmentStatus $to): AppointmentResource
    {
        $this->authorize('update', $appointment);

        $updated = (new TransitionAppointmentStatus)->handle(
            appointment: $appointment,
            to: $to,
            actor: $request->user(),
        );

        return new AppointmentResource($updated->load(self::WITH));
    }
}
