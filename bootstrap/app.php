<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureApiUserIsActive;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Http\Middleware\SetLocale;
use App\Http\Responses\ApiResponse;
use App\Integrations\Esim\Exceptions\ProviderException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->api(prepend: [
            AssignRequestId::class,
            SetLocale::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'ability' => CheckForAnyAbility::class,
            'abilities' => CheckAbilities::class,
            'idempotency' => EnsureIdempotencyKey::class,
            'active' => EnsureApiUserIsActive::class,
        ]);

        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainException $e): mixed {
            return ApiResponse::error($e->getMessage(), $e->httpStatus(), $e->context(), $e->errorCode());
        });

        $exceptions->render(function (ProviderException $e): mixed {
            return ApiResponse::error(
                'The connectivity provider could not complete this request.',
                $e->httpStatus(),
                [],
                $e->error->code,
            );
        });

        $exceptions->render(function (ValidationException $e): mixed {
            return ApiResponse::error($e->getMessage(), 422, $e->errors(), 'VALIDATION_ERROR');
        });

        $exceptions->render(function (AuthenticationException $e, Request $request): mixed {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', 401, [], 'UNAUTHENTICATED');
        });

        $exceptions->render(function (AuthorizationException $e, Request $request): mixed {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error($e->getMessage() ?: 'Forbidden.', 403, [], 'FORBIDDEN');
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request): mixed {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                409 => 'IDEMPOTENCY_CONFLICT',
                default => 'HTTP_ERROR',
            };

            return ApiResponse::error($e->getMessage() ?: 'Error', $status, [], $code);
        });
    })->create();
