<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keşif Rehberi açma anahtarı (chatbot bayraklarıyla aynı kalıp). Varsayılan
 * AÇIK; sorun çıkarsa .env'e AI_DISCOVERY_ENABLED=false yazmak yeterli.
 * Bayrak istek anında okunur (route:cache uyumlu).
 */
class EnsureDiscoveryGuideEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('ai.discovery_enabled'), 404);

        return $next($request);
    }
}
