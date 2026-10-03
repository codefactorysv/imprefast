<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['telegram/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            return response()->view('errors.upload-too-large', status: 413);
        });
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->expectsJson()) return null;
            $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 60);

            return response()->json([
                'message' => 'Se hicieron varios intentos en poco tiempo. Espera unos segundos y vuelve a intentarlo.',
                'retry_after' => $retryAfter,
            ], 429, ['Retry-After' => $retryAfter]);
        });
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->routeIs('orders.store') || ! $request->expectsJson()) return null;
            if ($exception instanceof ValidationException || $exception instanceof HttpExceptionInterface) return null;

            $supportId = Str::upper(Str::random(10));
            Log::error('No se pudo confirmar el pedido.', [
                'support_id' => $supportId,
                'exception_type' => $exception::class,
            ]);

            return response()->json([
                'message' => "No pudimos confirmar el pedido. Intenta nuevamente. Código de soporte: {$supportId}",
                'support_id' => $supportId,
            ], 500);
        });
    })->create();
