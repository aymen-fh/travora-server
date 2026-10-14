<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! is_string($key) || trim($key) === '') {
            abort(428, 'Idempotency-Key header is required.');
        }

        $key = trim($key);
        $fingerprint = hash('sha256', $request->getContent());
        $cacheKey = 'idempotency:'.hash('sha256', implode('|', [
            $user->id,
            $request->method(),
            $request->path(),
            $key,
        ]));

        $existing = Cache::get($cacheKey);

        if (is_string($existing) && $existing !== $fingerprint) {
            abort(409, 'Idempotency key was reused with a different payload.');
        }

        Cache::put($cacheKey, $fingerprint, now()->addDay());

        return $next($request);
    }
}
