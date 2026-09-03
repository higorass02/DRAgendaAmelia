<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // API pura, sem rota "login" de sessão/web — sem isso, um cliente que
        // não manda "Accept: application/json" derruba a app com 500 (tenta
        // gerar a URL de uma rota nomeada "login" que não existe) em vez de
        // simplesmente devolver 401.
        Authenticate::redirectUsing(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Exceptions de domínio (App\Exceptions) viram resposta HTTP aqui, num
        // lugar só — os Actions/controllers não precisam saber de status code.
        $exceptions->render(function (\App\Exceptions\InvalidStatusTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
        $exceptions->render(function (\App\Exceptions\OutsideAvailabilityException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
        $exceptions->render(function (\App\Exceptions\ScheduleConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        // Rede de segurança: qualquer exceção não mapeada acima (erro de SQL,
        // TypeError, etc.) NUNCA pode devolver a mensagem crua pro cliente —
        // ela costuma conter nome de tabela/coluna/host do banco. Isso vale
        // mesmo com APP_DEBUG=true (útil pra olhar o log local), porque o
        // João que reportou é sobre o que aparece NA TELA, não no terminal.
        // Os tipos abaixo já viram uma resposta seguro e específica por conta
        // do handler padrão do Laravel (404, 401, 403, 422, 429...) — esses
        // continuam intocados; só o resto (o "erro inesperado" de verdade)
        // é substituído por uma mensagem genérica.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $safe = $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \App\Exceptions\InvalidStatusTransitionException
                || $e instanceof \App\Exceptions\OutsideAvailabilityException
                || $e instanceof \App\Exceptions\ScheduleConflictException;

            if ($safe) {
                return null;
            }

            report($e);

            return response()->json(['message' => 'Algo deu errado no servidor. Tente novamente.'], 500);
        });
    })->create();
