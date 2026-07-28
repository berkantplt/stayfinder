<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chatbot v2 açma anahtarı. v1'inkinden AYRI: v1 dondurulmuşken v2'yi canlıda
 * kademeli açabilmek için. Bayrak istek anında okunur (route:cache uyumlu).
 */
class EnsureAiChatV2Enabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('ai.chat_v2_enabled'), 404);

        return $next($request);
    }
}
