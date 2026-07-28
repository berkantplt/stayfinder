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
