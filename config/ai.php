<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Model Konfigürasyonu
    |--------------------------------------------------------------------------
    |
    | Sistem farklı görevler için farklı OpenAI modellerini kullanabilsin
    | diye burada merkezleştirildi. Pahalı görevler (intent extraction'da
    | doğruluk kritik) için gpt-4o; ucuz/yüksek-hacimli görevler için
    | gpt-4o-mini default.
    |
    | Maliyet kabaca (input tokens, 2026 Q1 fiyatları):
    |   gpt-4o          ~$2.50 / 1M
    |   gpt-4o-mini     ~$0.15 / 1M  (16-17x daha ucuz)
    |
    */

    // Intent extraction — niyet çıkarma kalitesi search sonucunun ~%40'ını belirler.
    // Negasyon, çelişki ve çoklu kriter doğruluğu için gpt-4o tercih edilir.
    'intent_model' => env('AI_INTENT_MODEL', 'gpt-4o'),

    // AI yorum üretimi — RAG bağlamı yeterince güçlü olduğu için gpt-4o-mini yeterli.
    'comment_model' => env('AI_COMMENT_MODEL', 'gpt-4o-mini'),

    // Tur embedding'i — embeddings ayrı model ailesi.
    'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),

    // Destinasyon zenginleştirme job'ı — yüksek hacim, mini yeterli.
    'destination_enrichment_model' => env('AI_DEST_MODEL', 'gpt-4o-mini'),

    // Tur URL'sinden içe aktarma — karışık HTML metninden çoklu alan çıkarımı;
    // doğruluk için gpt-4o.
    'import_model' => env('AI_IMPORT_MODEL', 'gpt-4o'),

    // Tur URL içe aktarma — okuyucu servisi: sayfayı gerçek tarayıcıyla render edip
    // temiz Markdown döndürür (JS-render + gürültü temizliği → daha doğru çıkarım).
    // Boş bırakılırsa okuyucu atlanır, doğrudan fetch kullanılır.
    // Jina Reader ücretsiz tier ile key gerektirmez; key limiti artırır.
    'import_reader_url' => env('AI_IMPORT_READER_URL', 'https://r.jina.ai/'),
    'import_reader_key' => env('JINA_API_KEY'),
];
