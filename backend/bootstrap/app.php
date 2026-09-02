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
    })->create();
