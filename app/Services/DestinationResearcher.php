<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class DestinationResearcher
{
    /**
     * Bir destinasyon için web araştırması yapar ve Türk turistler için
     * structured turistik bilgi döndürür. Kalıcılığı çağıran tarafa bırakır.
     *
     * @return array{
     *   summary: string,
     *   best_seasons: array<int,string>,
     *   must_see: array<int,string>,
     *   avoid: array<int,string>,
     *   visa_notes: ?string,
     *   vibe: ?string,
     *   price_anchor_try: ?string,
     *   audience_fit: array<string,string>,
     *   safety_notes: ?string,
     *   sources: array<int,string>,
     * }|null
     */
    public function research(string $name, ?string $country = null): ?array
    {
        $label = trim($name . ($country ? ", {$country}" : ''));

        try {
            $response = OpenAI::responses()->create([
                'model' => 'gpt-4o-mini',
                'tools' => [[
                    'type' => 'web_search_preview',
                    'search_context_size' => 'medium',
                ]],
                'input' => [[
                    'role' => 'user',
                    'content' =>
                        "Türkiye'den gelen turistler için '{$label}' destinasyonu hakkında GÜNCEL ve PRATİK araştırma yap. " .
                        "Web'de güncel kaynaklara bak (turizm sitesi, blog, resmi vize sayfaları). " .
                        "Çıktıyı **yalnızca aşağıdaki JSON formatında** ver (markdown veya açıklama ekleme):\n\n" .
                        "{\n" .
                        "  \"summary\": \"2-3 cümle genel özet (üslubu, neye benzediği)\",\n" .
                        "  \"best_seasons\": [\"mart-mayıs\", \"ekim-kasım\"],\n" .
                        "  \"must_see\": [\"yer1\", \"yer2\", \"yer3\", ...] (3-6 madde),\n" .
                        "  \"avoid\": [\"sıcak yaz ayları\", \"belirli olumsuz durumlar\"] (2-4 madde),\n" .
                        "  \"visa_notes\": \"TC pasaportu için vize durumu, kaç gün, ücret vs (yoksa null)\",\n" .
                        "  \"vibe\": \"genel atmosfer (ör: 'lüks alışveriş + çöl macerası')\",\n" .
                        "  \"price_anchor_try\": \"TR'den ortalama tur fiyat aralığı (TL cinsinden, ör: '25-40 bin TL 4-5 gün')\",\n" .
                        "  \"audience_fit\": {\n" .
                        "    \"family\": \"çok uygun|uygun|zor\",\n" .
                        "    \"couple\": \"...\",\n" .
                        "    \"solo\": \"...\",\n" .
                        "    \"friends\": \"...\"\n" .
                        "  },\n" .
                        "  \"safety_notes\": \"güvenlik veya kültürel uyarılar (yoksa null)\",\n" .
                        "  \"sources\": [\"kullanılan URL'ler veya kaynak isimleri\"]\n" .
                        "}\n\n" .
                        "Sadece JSON dön, başka metin yazma.",
                ]],
            ]);

            $text = $this->extractText($response);
            if ($text === '') {
                Log::warning('[DestinationResearcher] Boş yanıt', ['destination' => $label]);
                return null;
            }

            $json = $this->extractJson($text);
            if (!is_array($json)) {
                Log::warning('[DestinationResearcher] JSON parse hatası', [
                    'destination' => $label,
                    'raw' => mb_substr($text, 0, 500),
                ]);
                return null;
            }

            return $this->normalize($json);
        } catch (\Throwable $e) {
            Log::error('[DestinationResearcher] Hata: ' . $e->getMessage(), [
                'destination' => $label,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function extractText($response): string
    {
        // Responses API çıktısı: output[] dizisinde her item bir mesaj/araç çağrısı.
        // Son "message" tipindeki item'ın content[].text alanlarını birleştir.
        $text = '';
        $output = $response->output ?? [];

        foreach ($output as $item) {
            $type = is_object($item) ? ($item->type ?? null) : ($item['type'] ?? null);
            if ($type !== 'message') continue;
            $contents = is_object($item) ? ($item->content ?? []) : ($item['content'] ?? []);
            foreach ($contents as $c) {
                $chunkText = is_object($c) ? ($c->text ?? null) : ($c['text'] ?? null);
                if (is_string($chunkText)) $text .= $chunkText;
            }
        }

        // Fallback: SDK'nın convenience property'leri varsa
        if ($text === '' && isset($response->outputText)) {
            $text = (string) $response->outputText;
        }

        return trim($text);
    }

    private function extractJson(string $text): ?array
    {
        // Markdown code fence varsa temizle
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text)) ?? $text;

        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        // İlk { ... } bloğunu yakalayıp tekrar dene
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }

    private function normalize(array $raw): array
    {
        $strArray = function ($v): array {
            if (!is_array($v)) return [];
            return array_values(array_filter(array_map(
                fn($x) => is_string($x) ? trim($x) : '',
                $v
            )));
        };

        $audience = is_array($raw['audience_fit'] ?? null) ? $raw['audience_fit'] : [];
        $audienceClean = [];
        foreach (['family', 'couple', 'solo', 'friends'] as $key) {
            if (isset($audience[$key]) && is_string($audience[$key])) {
                $audienceClean[$key] = trim($audience[$key]);
            }
        }

        return [
            'summary'           => is_string($raw['summary'] ?? null) ? trim($raw['summary']) : '',
            'best_seasons'      => $strArray($raw['best_seasons'] ?? null),
            'must_see'          => $strArray($raw['must_see'] ?? null),
            'avoid'             => $strArray($raw['avoid'] ?? null),
            'visa_notes'        => is_string($raw['visa_notes'] ?? null) ? trim($raw['visa_notes']) : null,
            'vibe'              => is_string($raw['vibe'] ?? null) ? trim($raw['vibe']) : null,
            'price_anchor_try'  => is_string($raw['price_anchor_try'] ?? null) ? trim($raw['price_anchor_try']) : null,
            'audience_fit'      => $audienceClean,
            'safety_notes'      => is_string($raw['safety_notes'] ?? null) ? trim($raw['safety_notes']) : null,
            'sources'           => $strArray($raw['sources'] ?? null),
        ];
    }
}
