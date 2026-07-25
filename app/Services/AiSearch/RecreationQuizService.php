<?php

namespace App\Services\AiSearch;

use App\Models\AiSearchConversation;
use App\Models\User;

/**
 * Tatil Karakteri Testi — LLM'siz, deterministik rekreasyon profili.
 *
 * Boyut seti Türkçe LMS uyarlamasının 6 faktörü (Kırova & Yılmaz 2022) +
 * yenilik (en ayırt edici eksen) + gastronomi (davranışsal geçerliliği kanıtlı).
 * Skorlama kuralları:
 *  - Evrensel motife (dinlenme) tek soru: kaçış/sükûnet ayrımı s9'da yapılır.
 *  - Yenilik paydası 5 (kaydırak + çipler birikince erken tavan yapmasın).
 *  - Kaydıraklar mutlak ölçümdür (çip sayımının ipsatifliğini dengeler).
 * Soru metni/ağırlık değişirse VERSION artırılmalı — eski profiller karşılaştırma
 * dışı kalır.
 */
class RecreationQuizService
{
    public const VERSION = 1;

    public const META_KEY = '_recreation_profile';

    /** Persona öncelik sırası: beraberlikte üstteki kazanır. */
    private const PERSONAS = [
        'gastro' => 'Gurme Kâşif 🍽️',
        'kultur' => 'Kültür Gezgini 🏛️',
        'macera' => 'Adrenalin Avcısı 🧗',
        'sosyal' => 'Eğlence Elçisi 🎉',
        'prestij' => 'Trend Avcısı 📸',
        'yenilik' => 'Rota Dışı Kâşif 🧭',
        'sukunet' => 'Sahil Bilgesi 🌊',
        'kacis' => 'Şarj Ustası 🔋',
    ];

    private const AXIS_LABELS = [
        'yenilik' => 'Yenilik arayışı',
        'kultur' => 'Kültür & keşif',
        'macera' => 'Macera',
        'sosyal' => 'Sosyallik',
        'prestij' => 'Prestij',
        'kacis' => 'Stresten kaçış',
        'sukunet' => 'Sükûnet',
        'gastro' => 'Gastronomi',
    ];

    /** Görsel kart destesi: eksen ağırlıkları + içerik etiketi. */
    private const CARDS = [
        'koy' => ['em' => '🏝️', 't' => 'Sessiz koy', 's' => 'dalga sesi, kitap', 'ax' => ['sukunet' => 2], 'tag' => 'deniz'],
        'pazar' => ['em' => '🍜', 't' => 'Sokak lezzetleri', 's' => 'yerel pazar, tezgâh', 'ax' => ['gastro' => 2], 'tag' => null],
        'antik' => ['em' => '🏛️', 't' => 'Antik kent', 's' => 'taş sokaklar, tarih', 'ax' => ['kultur' => 2], 'tag' => null],
        'rafting' => ['em' => '🧗', 't' => 'Rafting / tırmanış', 's' => 'nabız yükselten', 'ax' => ['macera' => 2], 'tag' => 'doga'],
        'fest' => ['em' => '🎉', 't' => 'Plaj festivali', 's' => 'müzik, kalabalık', 'ax' => ['sosyal' => 2], 'tag' => null],
        'zirve' => ['em' => '🌄', 't' => 'Gündoğumu zirvesi', 's' => 'bulutların üstü', 'ax' => ['yenilik' => 1], 'tag' => 'doga'],
        'cadde' => ['em' => '🛍️', 't' => 'Meşhur cadde', 's' => 'vitrinler, kafeler', 'ax' => ['prestij' => 1], 'tag' => 'sehir'],
        'spa' => ['em' => '🧖', 't' => 'Termal & SPA', 's' => 'buhar, sessizlik', 'ax' => ['sukunet' => 1, 'kacis' => 1], 'tag' => null],
    ];

    /** Çip sorularının seçenekleri: [metin, eksen ağırlıkları, (s7 için) deneyim kodu]. */
    private const CHIP_OPTIONS = [
        's4' => [
            ['Hemen denerim 😋', ['gastro' => 2, 'yenilik' => 1]],
            ['Önce yorumlarına bakarım', ['gastro' => 1]],
            ['Güvenli restoranı seçerim', []],
        ],
        's5' => [
            ['Kalabalık & canlı müzik', ['sosyal' => 2]],
            ['Küçük grupla uzun sofra', ['sosyal' => 1, 'gastro' => 1]],
            ['Partnerle sakin akşam', ['sukunet' => 1]],
            ['Tek başıma gece keşfi', ['yenilik' => 1]],
        ],
        's6' => [
            ['"Herkes nerede olduğumuzu sordu"', ['prestij' => 2]],
            ['"Kimsenin bilmediği bir yer bulduk"', ['yenilik' => 2]],
            ['"Fotoğraf mı? Çekmeyi unuttum"', ['sukunet' => 1]],
        ],
        's7' => [
            ['İlk büyük tatillerim ✈️', [], 'az'],
            ['Yılda 1-2, alışkınım', [], 'orta'],
            ['Çok gezdim, pasaport dolu', [], 'cok'],
        ],
        's8a' => [
            ['Yerel hayata karışmak', ['kultur' => 2, 'sosyal' => 1]],
            ['Doğanın derinine inmek', ['macera' => 1]],
            ['İyi planlanmış konfor', ['kacis' => 1]],
        ],
        's8b' => [
            ['Her şeyin ayarlanmış olması', ['kacis' => 1]],
            ['Heyecan verici yeni deneyimler', ['macera' => 1, 'yenilik' => 1]],
            ['Arkadaşlarla eğlence', ['sosyal' => 2]],
        ],
        's9' => [
            ['Yoğunluktan kaçmak', ['kacis' => 2]],
            ['Sükûnetin kendisi', ['sukunet' => 2]],
            ['Yeni şeyler yaşamak', ['yenilik' => 2]],
        ],
    ];

    /**
     * Ön yüzün render edeceği veri-güdümlü soru tanımı. Ağırlıklar bilerek
     * dahil edilmez — skorlama yalnız sunucuda yapılır.
     */
    public function definition(): array
    {
        $chip = fn (string $key) => array_map(fn ($o) => $o[0], self::CHIP_OPTIONS[$key]);

        return [
            'version' => self::VERSION,
            'axis_labels' => self::AXIS_LABELS,
            'steps' => [
                ['key' => 's1', 'type' => 'slider', 'q' => 'Hayalindeki bir sonraki tatil hangi uca yakın?', 'l' => 'Tanıdık & garanti 🏠', 'r' => '🌍 Egzotik & bilinmedik'],
                ['key' => 's2', 'type' => 'cards', 'q' => 'Seni en çok çeken 3 kareyi seç', 'pick' => 3,
                    'cards' => collect(self::CARDS)->map(fn ($c, $id) => ['id' => $id, 'em' => $c['em'], 't' => $c['t'], 's' => $c['s']])->values()->all()],
                ['key' => 's3', 'type' => 'slider', 'q' => 'Tatil tempon?', 'l' => 'Tam şezlong 🏖️', 'r' => '🏃 Maraton gezgin'],
                ['key' => 's4', 'type' => 'chips', 'q' => 'Bilmediğin bir sokak lezzeti gördün…', 'opts' => $chip('s4')],
                ['key' => 's5', 'type' => 'chips', 'q' => 'Tatilde ideal akşam?', 'opts' => $chip('s5')],
                ['key' => 's6', 'type' => 'chips', 'q' => 'Dönüşte en keyifli cümle hangisi olurdu?', 'opts' => $chip('s6')],
                // codes: ön yüz dallanmayı seçenek index'i yerine bu kodlarla kurar
                ['key' => 's7', 'type' => 'chips', 'q' => 'Seyahat geçmişin?', 'opts' => $chip('s7'), 'branch' => true,
                    'codes' => array_map(fn ($o) => $o[2], self::CHIP_OPTIONS['s7'])],
                // s8a/s8b: ön yüz s7 cevabına göre birini gösterir (az → s8b, diğerleri → s8a)
                ['key' => 's8a', 'type' => 'chips', 'q' => 'Yeni bir yerde seni en çok ne besler?', 'opts' => $chip('s8a'), 'branch_of' => 's7', 'branch_when' => ['orta', 'cok']],
                ['key' => 's8b', 'type' => 'chips', 'q' => 'Tatilde hangisi daha önemli?', 'opts' => $chip('s8b'), 'branch_of' => 's7', 'branch_when' => ['az']],
                ['key' => 's9', 'type' => 'chips', 'q' => 'Tatile çıkma sebebin en çok…', 'opts' => $chip('s9')],
            ],
        ];
    }

    /**
     * Ham cevaplardan profil üretir. $answers: s1/s3 → 0-100 int,
     * s2 → kart id dizisi, çipler → seçenek index'i.
     *
     * @return array{vector: array<string,float>, tempo: int, tags: string[], persona: array{key:string,label:string}}
     */
    public function score(array $answers): array
    {
        $pts = array_fill_keys(array_keys(self::AXIS_LABELS), 0.0);
        $tags = [];

        // s1 yenilik kaydırağı — mutlak ölçüm, 2 puana ölçeklenir
        $pts['yenilik'] += max(0, min(100, (int) ($answers['s1'] ?? 50))) / 100 * 2;

        // s2 görsel kartlar — bilinmeyen id'ler yok sayılır, en fazla 3 kart
        foreach (array_slice((array) ($answers['s2'] ?? []), 0, 3) as $cardId) {
            $card = self::CARDS[$cardId] ?? null;
            if (! $card) {
                continue;
            }
            foreach ($card['ax'] as $axis => $w) {
                $pts[$axis] += $w;
            }
            if ($card['tag']) {
                $tags[] = $card['tag'];
            }
        }

        // Çip soruları — s7 deneyim sorusu puan vermez, dalı belirler
        $experience = null;
        foreach (self::CHIP_OPTIONS as $key => $options) {
            if (! array_key_exists($key, $answers)) {
                continue;
            }
            $opt = $options[(int) $answers[$key]] ?? null;
            if (! $opt) {
                continue;
            }
            foreach ($opt[1] as $axis => $w) {
                $pts[$axis] += $w;
            }
            if ($key === 's7') {
                $experience = $opt[2] ?? null;
            }
        }

        // Yanlış dalın cevabı geldiyse (ör. deneyimli kullanıcıdan s8b) yok sayılmaz —
        // ağırlıkları zaten aynı eksen ailesinde, çifte cevap ise normalizasyonda törpülenir.
        $vector = [];
        foreach ($pts as $axis => $p) {
            $vector[$axis] = round(min(1.0, $p / ($axis === 'yenilik' ? 5 : 4)), 2);
        }

        return [
            'vector' => $vector,
            'tempo' => max(0, min(100, (int) ($answers['s3'] ?? 50))),
            'tags' => array_values(array_unique($tags)),
            'persona' => $this->personaFor($vector),
            'experience' => $experience,
        ];
    }

    /** @param array<string,float> $vector */
    public function personaFor(array $vector): array
    {
        $bestKey = array_key_first(self::PERSONAS);
        $bestVal = -1.0;
        foreach (self::PERSONAS as $axis => $label) {
            if (($vector[$axis] ?? 0) > $bestVal) {
                $bestVal = (float) $vector[$axis];
                $bestKey = $axis;
            }
        }

        return ['key' => $bestKey, 'label' => self::PERSONAS[$bestKey]];
    }

    /**
     * Test sonrası otomatik aramayı besleyecek doğal sorgu — en güçlü iki
     * eksenden kurulur; hem kullanıcı mesajı olarak okunur hem embedding'e
     * gerçek sinyal verir.
     */
    public function followupQuery(array $vector, array $tags = []): string
    {
        $phrases = [
            'sukunet' => 'sakin ve huzurlu',
            'yenilik' => 'az bilinen',
            'kultur' => 'kültür ve tarih dolu',
            'macera' => 'macera ve aktivite dolu',
            'sosyal' => 'eğlenceli ve hareketli',
            'gastro' => 'yerel lezzet odaklı',
            'kacis' => 'dinlendirici',
            'prestij' => 'popüler',
        ];

        $sorted = collect($vector)->sortDesc()->keys()->all();
        $parts = array_values(array_filter([
            $phrases[$sorted[0] ?? ''] ?? null,
            $phrases[$sorted[1] ?? ''] ?? null,
        ]));

        $suffix = in_array('doga', $tags, true) ? ' doğayla iç içe' : (in_array('deniz', $tags, true) ? ' deniz kenarında' : '');

        return 'Bana '.implode(', ', $parts).$suffix.' bir tur öner';
    }

    /**
     * Profili kalıcılaştırır: girişli kullanıcıda users.recreation_profile,
     * her durumda konuşma meta'sına da yazılır (arama bonusu oradan okunur).
     */
    public function store(?User $user, ?AiSearchConversation $conversation, array $result, array $answers): array
    {
        $profile = [
            'vector' => $result['vector'],
            'tempo' => $result['tempo'],
            'tags' => $result['tags'],
            'persona' => $result['persona'],
            'answers' => $answers,
            'quiz_version' => self::VERSION,
            'confirmed_at' => now()->toIso8601String(),
        ];

        if ($user) {
            $user->forceFill(['recreation_profile' => $profile])->save();
        }

        if ($conversation) {
            $intent = $conversation->current_intent ?? [];
            $intent[self::META_KEY] = $profile;
            $conversation->update(['current_intent' => $intent]);
        }

        return $profile;
    }

    /** Arama skorlaması için profil: kullanıcı > konuşma meta önceliğiyle. */
    public function profileFor(?User $user, ?array $conversationIntent): ?array
    {
        $profile = $user?->recreation_profile;
        if (is_array($profile) && ! empty($profile['vector'])) {
            return $profile;
        }

        $metaProfile = $conversationIntent[self::META_KEY] ?? null;

        return (is_array($metaProfile) && ! empty($metaProfile['vector'])) ? $metaProfile : null;
    }
}
