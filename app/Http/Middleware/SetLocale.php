<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request->header('Accept-Language', '')));

        return $next($request);
    }

    private function resolveLocale(string $header): string
    {
        // Take highest-priority tag from e.g. "pt-BR,pt;q=0.9,en;q=0.8"
        $primary = strtolower(trim(explode(',', $header)[0]));
        $tag = trim(explode(';', $primary)[0]);

        if ($tag === 'pt-br' || $tag === 'pt') {
            return 'pt_BR';
        }

        return 'en';
    }
}
