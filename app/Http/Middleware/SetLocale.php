<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale as AppLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->getPreferredLanguage(['en', 'ar']);
        $userLocale = $request->user()?->preferred_language;
        $locale = $userLocale instanceof AppLocale ? $userLocale->value : ($header ?? 'en');

        app()->setLocale($locale);

        return $next($request);
    }
}
