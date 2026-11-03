<?php

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Exceptions\MaxIterationsReached;
use App\Tutor\AgentIncomplete;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // El modelo no respondió (timeout, 429): servicio no disponible por ahora.
        $exceptions->render(fn (LlmException $e) => response()->json(
            ['message' => 'El modelo de IA no está disponible, intenta en unos segundos.', 'detail' => $e->getMessage()],
            503,
        ));

        // El modelo respondió, pero no completó la tarea: error del servicio que llamamos.
        $exceptions->render(fn (AgentIncomplete|MaxIterationsReached $e) => response()->json(['message' => $e->getMessage()], 502));
    })->create();
