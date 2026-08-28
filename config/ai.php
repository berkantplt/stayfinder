<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Model Konfigürasyonu
    |--------------------------------------------------------------------------
    |
    | Sistem farklı görevler için farklı OpenAI modellerini kullanabilsin
    | diye burada merkezleştirildi. Doğruluk-kritik ve tek-seferlik görevler
    | gpt-5.4-mini; yüksek-hacimli + gecikmeye duyarlı canlı görevler
    | (yorum stream'i, router) gpt-4o-mini default.
    |
    | Maliyet kabaca (input / output, 1M token, 2026 Q3 fiyatları):
    |   gpt-4o (legacy)  $2.50 / $10.00
    |   gpt-5.4-mini     $0.75 / $4.50  (görünmez düşünme tokenları output'tan sayılır)
    |   gpt-4o-mini      $0.15 / $0.60
    |
    | gpt-5/o-serisi çağrıları max_tokens + temperature kabul etmez —
    | parametreler App\Support\OpenAiChatParams üzerinden kurulmalı.
    |
    */

    // ❄️ CHATBOT DONDURULDU (2026-07-28, kullanıcı kararı): v1 sohbet asistanı
    // yayından kaldırıldı — yerine yeni mimari kurulacak. Kod ve veri yerinde
    // duruyor; geri açmak için .env'e AI_CHAT_ENABLED=true yazmak yeterli.
    // Kapsam: yüzen widget + /yapay-zeka-arama sayfası + mesaj uçları.
    // ETKİLENMEZ: tur eşleştirme testi (/tatil-karakteri) ve rubrik puanlama.
    'chat_enabled' => env('AI_CHAT_ENABLED', false),

    // v2 KENDİ bayrağı: chat_enabled v1'i kapatıyor, aynı bayrak kullanılırsa
    // v1 kapalıyken v2 canlıda denenemezdi. İkisi bağımsız açılıp kapanır.
    'chat_v2_enabled' => env('AI_CHAT_V2_ENABLED', false),

    // ⏸️ Tur eşleştirme testi ASKIDA (2026-08-11, kullanıcı kararı): ana sayfadaki
    // "Hangi tur sana uygun?" girişi gizlendi. Kod, rubrik ve testler yerinde;
    // geri açmak için .env'e AI_QUIZ_ENABLED=true yazmak yeterli.
    //
    // /tatil-karakteri UCU BİLEREK AÇIK bırakıldı: LLM'siz, deterministik ve
    // maliyetsiz. Kapatmak, bayrak açıldığında modalin sessizce boş dönmesi
    // riskini getirirdi (bkz. sohbet v1'de yaşanan durum).
    'quiz_enabled' => env('AI_QUIZ_ENABLED', false),

    // ---- Keşif Rehberi (/kesif-rehberi) ----
    // Şehir + gün girdisinden günlere bölünmüş içerik planı. Varsayılan AÇIK;
    // kapatmak için AI_DISCOVERY_ENABLED=false (uçlar 404 döner, kod yerinde).
    'discovery_enabled' => env('AI_DISCOVERY_ENABLED', true),

    // Rehber üretim modeli — tek seferlik, cache'lenen, kalite-kritik üretim:
    // destinasyon zenginleştirmeyle aynı gerekçeyle AI_DEST_MODEL'i izler.
    'discovery_model' => env('AI_DISCOVERY_MODEL', env('AI_DEST_MODEL', 'gpt-5.4-mini')),

    // ---- Chatbot v2 (araç çağırma mimarisi, bkz. CHATBOT_V2.md) ----
    // Konuşan yüzey GÜÇLÜ model ister: v1'de burası gpt-4o-mini'ydi ve
    // "istediğim zekada değil" şikayetinin birinci sebebiydi. Mesaj başına
    // ~1,5 kuruş; eval setiyle mini'ye düşürmeyi denemek serbest.
    'chat_agent_model' => env('AI_CHAT_AGENT_MODEL', 'gpt-5.4'),

    // Araç döngüsü üst sınırı — aşılırsa model elindekiyle cevap yazmak zorunda
    // kalır (sonsuz döngü ve süresiz bekleme koruması).
    'chat_max_tool_rounds' => (int) env('AI_CHAT_MAX_TOOL_ROUNDS', 3),

    // Eval hakemi — değerlendirdiği botla AYNI model olmasın: model kendi
    // hatasını onaylama eğilimindedir. Ayrıca hakem işi ucuz (tek JSON kararı).
    'eval_judge_model' => env('AI_EVAL_JUDGE_MODEL', 'gpt-5.4-mini'),

    // Rubrik tur puanlama — kendi anahtarı olsun ki tur karakterini ucuzlatmak
    // rubriği de düşürmesin (ikisi varsayılanda aynı modele çıkar).
    'rubric_model' => env('AI_RUBRIC_MODEL', env('AI_DEST_MODEL', 'gpt-5.4-mini')),

    // Intent extraction — niyet çıkarma kalitesi search sonucunun ~%40'ını belirler.
    // 2026-07: gpt-4o → gpt-5.4-mini (import'taki kanıt sonrası; girdi 3.3x,
    // çıktı 2.2x ucuz ve güncel nesil). Geri dönüş: AI_INTENT_MODEL=gpt-4o.
    'intent_model' => env('AI_INTENT_MODEL', 'gpt-5.4-mini'),

    // AI yorum üretimi — RAG bağlamı yeterince güçlü olduğu için gpt-4o-mini yeterli.
    'comment_model' => env('AI_COMMENT_MODEL', 'gpt-4o-mini'),

    // Mesaj yönlendirici + arama-dışı cevaplar (tur sorusu/kıyas/site/sohbet) —
    // sınıflandırma ve topraklanmış QA görevleri; mini yeterli ve ucuz.
    'router_model' => env('AI_ROUTER_MODEL', 'gpt-4o-mini'),

    // LLM re-ranker: eşik üstü ilk 15 adayı niyete göre yeniden puanlar (mini).
    // Kapatmak için AI_RERANK=false.
    'rerank_enabled' => env('AI_RERANK', true),

    // Tur embedding'i — embeddings ayrı model ailesi.
    'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),

    // Destinasyon zenginleştirme job'ı — şehir başına tek çağrı, kalıcı veri
    // üretir (arka plan, gecikme önemsiz): kalite için gpt-5.4-mini.
    'destination_enrichment_model' => env('AI_DEST_MODEL', 'gpt-5.4-mini'),

    // Tur karakteri job'ı — tur başına ömür boyu 1 çağrı (tempo + karakter özeti);
    // şehir profilleri hazır veri olarak verilir, model uydurmaz. Kalıcı veri →
    // destinasyon profiliyle aynı gerekçeyle gpt-5.4-mini (AI_DEST_MODEL'i izler).
    'tour_character_model' => env('AI_TOUR_CHARACTER_MODEL', env('AI_DEST_MODEL', 'gpt-5.4-mini')),

    // Tur URL'sinden içe aktarma — karışık HTML metninden çoklu alan çıkarımı.
    // 2026-07: gpt-4o → gpt-5.4-mini (gpt-4o wind-down sürecinde; 5.4-mini zeka
    // endeksi 40 vs 11, maliyet ~%65 düşük; reasoning parametreleri chatParams'ta).
    'import_model' => env('AI_IMPORT_MODEL', 'gpt-5.4-mini'),

    // Fiyat matrisi fallback çağrısı (deterministik parser boş dönerse) — sayısal
    // hassasiyet kritik (eski/yeni fiyat eşleşmesi); şema/araç uyumu sınıfının
    // en güçlüsü olan gpt-5.4-mini (τ2-bench 93.4) kullanılır.
    'import_pricing_model' => env('AI_IMPORT_PRICING_MODEL', 'gpt-5.4-mini'),

    // Tur URL içe aktarma — okuyucu servisi: sayfayı gerçek tarayıcıyla render edip
    // temiz Markdown döndürür (JS-render + gürültü temizliği → daha doğru çıkarım).
    // Boş bırakılırsa okuyucu atlanır, doğrudan fetch kullanılır.
    // Jina Reader ücretsiz tier ile key gerektirmez; key limiti artırır.
    'import_reader_url' => env('AI_IMPORT_READER_URL', 'https://r.jina.ai/'),
    'import_reader_key' => env('JINA_API_KEY'),

    // "Derin tarama": Firecrawl gerçek tarayıcıda render + scroll/wait yapar; açılır
    // menülerdeki (tarih seçici vb.) içeriği de yükleyip temiz markdown döndürür.
    // Key yoksa derin tarama atlanır, normal yola düşülür.
    'import_firecrawl_url' => env('FIRECRAWL_API_URL', 'https://api.firecrawl.dev/v1/scrape'),
    'import_firecrawl_key' => env('FIRECRAWL_API_KEY'),
];
