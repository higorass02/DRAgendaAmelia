<?php

namespace App\Support\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Convenções compartilhadas de listagem (paginação com tamanho de página
 * ajustável + ordenação por coluna) usadas em todo endpoint index() da API —
 * ver CLAUDE.md, seção 9 (paginação/busca em todas as telas de listagem).
 */
class ListQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * `per_page` vem do cliente (seletor de itens por página); limitado pra
     * não virar um jeito de baixar a tabela inteira de uma vez.
     */
    public static function perPage(Request $request, int $default = 15): int
    {
        $perPage = $request->integer('per_page', $default);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * `$sortable` mapeia o nome aceito na query string pra coluna real (ou
     * expressão) usada no ORDER BY — nunca usa a coluna vinda da request
     * direto, pra não abrir a query pra nomes de coluna arbitrários.
     *
     * @param  array<string, string>  $sortable
     */
    public static function applySort(
        Builder $query,
        Request $request,
        array $sortable,
        string $defaultKey,
        string $defaultDirection = 'asc',
    ): Builder {
        $key = $request->filled('sort') ? $request->string('sort')->toString() : $defaultKey;
        $direction = strtolower($request->string('direction', $defaultDirection)->toString()) === 'desc' ? 'desc' : 'asc';

        $column = $sortable[$key] ?? $sortable[$defaultKey];

        return $query->orderBy($column, $direction);
    }
}
