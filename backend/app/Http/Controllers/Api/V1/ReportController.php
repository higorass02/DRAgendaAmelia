<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Reports\AppointmentReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    #[OA\Get(
        path: '/reports',
        tags: ['Appointments'],
        summary: 'Relatórios agregados (no-show, cancelamentos, remarcação, ocupação)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(
                name: 'professional_id[]',
                in: 'query',
                description: 'Um ou mais IDs de profissional (multi-seleção)',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
            ),
            new OA\Parameter(
                name: 'patient_id[]',
                in: 'query',
                description: 'Um ou mais IDs de paciente (multi-seleção)',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Métricas agregadas do período (padrão: últimos 30 dias)'),
            new OA\Response(response: 403, description: 'Sem permissão (só staff)'),
        ]
    )]
    public function index(Request $request, AppointmentReportService $service): JsonResponse
    {
        if (! $request->user()->hasStaffAccess()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $from = $request->filled('from') ? Carbon::parse($request->string('from'))->startOfDay() : now()->subDays(30);
        $to = $request->filled('to') ? Carbon::parse($request->string('to'))->endOfDay() : now();

        return response()->json($service->build(
            $from,
            $to,
            array_map('intval', (array) $request->query('professional_id', [])),
            array_map('intval', (array) $request->query('patient_id', [])),
        ));
    }
}
