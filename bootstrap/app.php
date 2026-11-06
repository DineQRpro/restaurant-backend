<?php

use App\Exceptions\BusinessConflictException;
use App\Exceptions\PasswordChangeRequiredException;
use App\Http\Middleware\CheckPermission;
use App\Services\Auth\PosLoginService;
use App\Http\Middleware\EnsureAdminWebAccess;
use App\Http\Middleware\EnsureCounterWebAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'admin.area' => EnsureAdminWebAccess::class,
            'counter.area' => EnsureCounterWebAccess::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->redirectUsersTo(function () {
            return app(PosLoginService::class)->dashboardUrl(auth()->user())
                ?? route('auth.unavailable');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($e instanceof PasswordChangeRequiredException && ! $request->is('api/*') && ! $request->expectsJson()) {
                return redirect()
                    ->route('password.change')
                    ->withInput($request->only('username'))
                    ->withErrors($e->errors());
            }

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $payload = [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ];

            if ($e instanceof PasswordChangeRequiredException) {
                $payload['code'] = PasswordChangeRequiredException::CODE;
            }

            return response()->json($payload, $e->status);
        });

        $exceptions->render(function (BusinessConflictException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 409);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The requested resource was not found.',
                'errors' => (object) [],
            ], 404);
        });
    })->create();
