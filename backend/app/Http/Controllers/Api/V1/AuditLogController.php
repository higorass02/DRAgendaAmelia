<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Http\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trilha de auditoria — só admin (App\Policies via checagem inline, mesmo
 * padrão do ReportController). Read-only por design: não existe rota de
 * update/delete, o log é imutável.
 */
class AuditLogController extends Controller
{
    private const SORTABLE = [
        'created_at' => 'created_at',
        'action' => 'action',
    ];

    #[OA\Get(
        path: '/audit-logs',
        tags: ['Audit'],
        summary: 'Trilha de auditoria (admin) — quem fez o quê, quando',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['created_at', 'action'])),
            new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'actor_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'action', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'subject_type', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada da trilha de auditoria'),
            new OA\Response(response: 403, description: 'Sem permissão (só admin)'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        if (! $request->user()->isAdmin()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $logs = AuditLog::query()
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->tap(fn ($q) => ListQuery::applySort($q, $request, self::SORTABLE, 'created_at', 'desc'))
            ->paginate(ListQuery::perPage($request));

        return AuditLogResource::collection($logs);
    }
}
