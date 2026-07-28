<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chatbot dondurma anahtarı. config('ai.chat_enabled') kapalıyken sohbet
 * uçları 404 döner — kod ve veri silinmez, .env bayrağıyla geri açılır.
 *
 * Bayrak İSTEK ANINDA okunur: route:cache alınmış olsa bile .env değişimi
 * anında etkili olur (route dosyasında if ile sarmalamanın aksine).
 */
class EnsureAiChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('ai.chat_enabled'), 404);

        return $next($request);
    }
}
