<?php

namespace App\Services\Chat\Tools;

/**
 * Chatbot v2 aracı. Model düşünür ve yazar; GERÇEKLERİ araçlardan alır.
 *
 * Her araç deterministiktir: aynı girdi her zaman aynı çıktıyı verir ve
 * LLM çağırmaz. Böylece her biri OpenAI olmadan birim testiyle doğrulanır.
 */
interface ChatTool
{
    /** OpenAI function adı (model bu adla çağırır). */
    public static function name(): string;

    /** OpenAI function şeması: {type:function, function:{name, description, parameters}}. */
    public static function schema(): array;

    /**
     * @param  array<string, mixed>  $args  modelin verdiği argümanlar (doğrulanmamış)
     * @return array<string, mixed>  modele geri verilecek yapılandırılmış sonuç
     */
    public function run(array $args): array;
}
