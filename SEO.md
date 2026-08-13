# turXtur SEO Planı

> Hazırlanma tarihi: 2026-08-11
> Hedef: "her kategoride, her şehirde, her turda aramalarda en üstte olmak"

---

## 0. Durum tespiti — ölçülen rakamlar

> ⚠️ **ÖNEMLİ DÜZELTME (uygulama sırasında ortaya çıktı).**
> Aşağıdaki sayılar **yerel geliştirme veritabanından** okundu ve bu veritabanı
> büyük ölçüde **demo/seed verisi**: acentalar "Jolly Tur", "ETS Tur", "Setur",
> "Tatil Sepeti" adlarını taşıyor (gerçek rakip firmaların adları), aralarında
> "Debug TopK" adlı bir test acentası var, 36 tur tek seferde 2026-02-25'te
> oluşturulmuş ve bir turun destinasyonu "Test".
>
> Yani **Faz 0'daki eksiklik oranları canlı siteyi tarif etmiyor olabilir.**
> Faz 0'a başlamadan önce aynı ölçüm canlı veritabanında tekrarlanmalı:
>
> ```bash
> php artisan tinker --execute="echo 'Tur: '.App\Models\Tour::active()->count().' | Kategorisiz: '.App\Models\Tour::active()->whereNull('category_id')->count().' | Itinerary bos: '.App\Models\Tour::active()->where(fn(\$q)=>\$q->whereNull('itinerary')->orWhere('itinerary',''))->count();"
> ```
>
> Ayrıca canlıda gerçek rakip firma adlarıyla demo acenta/tur varsa bunlar
> **yayından kaldırılmalı** — başkasının ticari unvanıyla sahte tur listelemek
> hem marka hakkı hem yanıltıcı içerik sorunudur, SEO'dan önce gelen bir konu.
>
> **Faz 1 (teknik) bu belirsizlikten etkilenmez** — kod düzeyinde iş, veriden
> bağımsız. Bu yüzden Faz 1 tamamlandı (bkz. aşağıdaki bölüm).

Plan varsayıma değil, veritabanından okunan şu sayılara dayanıyor:

| Ölçüm | Değer | Anlamı |
|---|---|---|
| Aktif tur | **92** | Rakipler (Etstur, Tatilsepeti, Setur) 5.000–20.000 bandında |
| Kategorisi olmayan tur | **44 / 92 (%48)** | Yarısı hiçbir kategori sayfasında görünemez |
| Açıklaması 300 karakterden kısa tur | **74 / 92 (%80)** | Google için "thin content" |
| Günlük programı (`itinerary`) boş tur | **92 / 92 (%100)** | En değerli SEO içeriği tamamen yok |
| Kalkış şehri (`departure_city`) dolu tur | **0 / 92** | "İstanbul kalkışlı X turu" sayfası şu an üretilemez |
| Toplam yorum | **1** | Yıldızlı sonuç (rich result) için yetersiz |
| Blog yazısı | **0** | Bilgi amaçlı aramalarda hiç varlık yok |
| 10+ turu olan kategori | **1 / 35** | Kategori sayfalarının çoğu boş açılır |
| 3+ turu olan destinasyon | **8 / 50** | Destinasyon sayfalarının çoğu boş açılır |

Ayrıca `destination` alanı serbest metin ve kirli: `Kapadokya` ile `Kapadokya, Nevşehir` ayrı kayıt,
içeride `Test` adlı bir destinasyon canlı turda duruyor.

### Bundan çıkan tek cümlelik sonuç

**Sıralamayı engelleyen şey meta etiketler değil, envanter ve veri eksikliği.**
Teknik SEO bu sitede toplam açığın yaklaşık beşte biri. Kalanı içerik.

### "Her şehirde her kategoride 1. sıra" neden şu anda doğrudan hedeflenemez

35 kategori × 81 il = **2.835 sayfa**. Elde 92 tur var. Bu sayfaların %99'u boş veya
1–2 turlu açılır. Google bunu *doorway pages* (kapı sayfası) olarak sınıflandırır ve bu,
spam politikalarında açıkça yazan bir ihlaldir. Sonuç sıralama değil, sitenin tümünün
değer kaybetmesi olur. Yani "hepsini bir anda üret" hamlesi hedefin tam tersini üretir.

### Bunun yerine kesin işleyen yol

Sayfayı **envanter doğduğunda** aç. Aşağıdaki planda her programatik sayfa bir eşiğe
bağlı (`≥3 tur`): eşiği geçen kombinasyon otomatik yayına girer, sitemap'e düşer,
iç link alır. Envanter büyüdükçe sayfa sayısı kendiliğinden büyür — sen hiçbir şey
yapmadan. 92 turda ~15 sayfa açılır, 1.000 turda ~250 sayfa, 5.000 turda ~800 sayfa.
Her biri gerçek içerikli olduğu için her biri sıralanabilir.

### Gerçekçi hedef tablosu

| Sorgu tipi | Örnek | 92 turla şans | Yol |
|---|---|---|---|
| Marka | "turXtur" | **1. sıra kesin** | Faz 1 |
| Uzun kuyruk | "kapadokya balon turu 2 gece fiyat" | **Yüksek** | Faz 1+2+3 |
| Karşılaştırma | "kapadokya turu fiyat karşılaştırma", "en ucuz dubai turu" | **Yüksek — asıl fırsat** | Faz 3 |
| Destinasyon | "dubai turları" | Orta | Faz 3+4 |
| Head term | "yurtdışı turları", "tur fiyatları" | **Envanter 1.000+ olmadan yok** | Faz 4+5 |

Karşılaştırma sorguları asıl fırsat: **12 acentanın fiyatını tek sayfada yan yana
gösterebilen tek site sensin.** Etstur kendi fiyatını gösterir, seninkini gösteremez.
Bu yapısal üstünlük ve savunulabilir tek konum burası.

---

## FAZ 0 — Veri bütünlüğü (bloke edici, önce bu)

Bu faz bitmeden diğer fazların çoğu boşa çalışır: sayfa üretilir ama içi boş olur.

| # | İş | Neden kritik |
|---|---|---|
| 0.1 | 44 kategorisiz turu kategorile (admin toplu atama ekranı + LLM ön-öneri) | Kategori sayfalarının yarısının içeriği bu turlarda |
| 0.2 | `departure_city` doldur (import + admin toplu düzenleme) | "İstanbul kalkışlı" sayfa ailesinin tek girdisi |
| 0.3 | `destination` alanını normalize et → `Destination` tablosuna bağla (`destination_id`) | `Kapadokya` / `Kapadokya, Nevşehir` ayrışması sayfa gücünü bölüyor |
| 0.4 | `Test` destinasyonlu 3 turu ve test kayıtlarını canlıdan temizle | Google'a test verisi indexletmek doğrudan kalite kaybı |
| 0.5 | `itinerary` doldur — 92 turun tamamı boş | Gün gün program = tur sayfasının en güçlü içeriği. İçe aktarmada zaten çekilebiliyor |
| 0.6 | 300 karakterden kısa 74 açıklamayı 600+ karaktere çıkar | Thin content eşiğinin altındasın |
| 0.7 | Görseli olmayan 3 tura görsel | Görsel yoksa hem OG hem Product schema kırık |

**Kilit nokta:** 0.5 ve 0.6 elle 92 kez yazılacak iş değil. `TourUrlImporter` bu alanları
zaten çekebiliyor ([TourUrlImporter.php](app/Services/TourImport/TourUrlImporter.php)) —
mevcut turlar için "yeniden zenginleştir" komutu yazılır, tek seferde çalışır.

---

## FAZ 1 — ✅ TAMAMLANDI

588 test geçiyor (mevcut 564 + 24 yeni SEO regresyon testi: `tests/Feature/SeoTest.php`).

### Yapılanlar

| İş | Dosya |
|---|---|
| Slug URL'leri + eski ID'den 301 (tur & acenta) | `app/Models/Tour.php`, `app/Models/Agency.php`, `TourController`, `AgencyController` |
| Mevcut slug'ları temizleyen komut | `app/Console/Commands/BackfillTourSlugs.php` |
| JSON-LD güvenli basım | `resources/views/partials/json-ld.blade.php` |
| Breadcrumb (görünür + BreadcrumbList) | `resources/views/partials/breadcrumb.blade.php` |
| Canonical / indexleme kuralları | `app/Support/Seo.php` |
| Sayfalama prev/next | `resources/views/partials/pagination-seo.blade.php` |
| Sitemap index + 5 bölüm haritası | `app/Http/Controllers/SitemapController.php`, `resources/views/sitemap/` |
| robots.txt (rota tabanlı) | `app/Http/Controllers/RobotsController.php` |
| https zorlama | `app/Providers/AppServiceProvider.php` |

### Uygulama sırasında bulunan, planda olmayan üç hata

1. **robots.txt acenta sayfalarını engelliyormuş.** `Disallow: /acenta` satırı
   ön-ek eşleşmesi yaptığı için herkese açık `/acentalar/{slug}` sayfalarını da
   kapsıyordu — 11 acenta sayfası baştan beri taramaya kapalıydı. `/acenta$` +
   `/acenta/` ayrımıyla düzeltildi, 20 yol için test edildi.
2. **`agencies/index.blade.php` view'ı var ama public rotası yok.** Acenta
   sayfalarına link veren hiçbir liste sayfası yok; yalnız tur sayfalarından
   erişiliyorlar (yetim sayfa). Sitemap'e eklendi, ama liste sayfası açılması
   ayrı bir iş — Faz 3'e alındı.
3. **Aynı başlıklı turlar birbirinin kopyası.** 12 başlık birden fazla turda
   tekrar ediyor (aynı turu 3-4 acenta satıyor). Bunlar birbiriyle yarışan
   near-duplicate sayfalar; doğru yapı Faz 3'teki karşılaştırma sayfası.

### Canlıya alma adımları

```bash
# 1. Slug'ları temizle (önce kuru çalışma ile gör)
php artisan seo:backfill-tour-slugs --dry-run
php artisan seo:backfill-tour-slugs

# 2. Cache'leri tazele
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan cache:forget sitemap_index_v2

# 3. Doğrula
curl -sI https://ALAN-ADI/turlar/1 | head -3        # 301 dönmeli
curl -s  https://ALAN-ADI/robots.txt                # mutlak Sitemap satırı
curl -s  https://ALAN-ADI/sitemap.xml               # 5 bölüm listelenmeli
```

> ⚠️ **`public/robots.txt` dosyası silindi** — statik dosya dururken Laravel
> rotası hiç çalışmaz. Sunucuda da silindiğinden emin ol, aksi halde eski
> (hatalı) robots.txt servis edilmeye devam eder.

> ⚠️ **`.env` içinde `APP_URL` canlı alan adı olmalı** ve `APP_ENV=production`.
> robots.txt üretim dışı ortamda tüm siteyi taramaya kapatır (kasıtlı koruma);
> `APP_ENV` yanlışsa canlı site Google'a tamamen kapanır.

### Sunucu (Plesk) tarafında yapılacaklar — koda ait değil

Bunlar web sunucusu katmanında çözülmeli; PHP'de yapmak her istekte gereksiz
Laravel açılışı demek:

- **www → kök domain 301** (ya da tersi; hangisi olursa olsun **tek yön**)
- **http → https 301**
- **Sondaki slash** için tek kural (`/turlar/` → `/turlar`)

---

## FAZ 1 — özgün plan (referans için)

### 1.1 Slug tabanlı URL'ler ⭐ en büyük tekil kazanç
- `Tour` modeline `getRouteKeyName() => 'slug'`. Slug üretimi [Tour.php:176](app/Models/Tour.php:176)'da zaten var, sadece kullanılmıyor.
- `Agency` için aynısı — slug var, kullanılmıyor.
- `/turlar/1247` → `/turlar/kapadokya-balon-turu-2-gece`
- Eski ID URL'lerinden **301** yönlendirme (index edilmiş olanlar kaybolmasın).
- Slug'daki rastgele 5 karakter son eki kaldırılıp çakışma durumunda sayısal sona geçilmeli (`-2`, `-3`); rastgele son ek anahtar kelimeyi seyreltiyor.

### 1.2 JSON-LD escape hatası ⚠️ şu an sessizce kırık
[tours/show.blade.php:13](resources/views/tours/show.blade.php:13) — `"name": "{{ $tour->title }}"`.
Başlıkta `"` veya `'` geçen ilk turda JSON parse edilemez ve Google o sayfanın **tüm**
yapısal verisini atar. Aynı hata `home.blade.php` ve `blog/show.blade.php`'de de var.
Çözüm: `@json()` ile veya `json_encode` edilmiş dizi ile bas.

### 1.3 Canonical ve sayfalama
- [app.blade.php:9](resources/views/layouts/app.blade.php:9) `url()->current()` query string'i atıyor →
  `?page=2` de 1. sayfaya canonical veriyor, Google 2+ sayfadaki turları yok sayıyor.
- Sayfalı listelerde canonical kendine (`?page=2` → `?page=2`), `rel=prev/next` eklenir.
- Filtre kombinasyonları (`?fiyat=&sure=&kalkis=`) sonsuz URL üretiyor, crawl bütçesini yakıyor:
  - Beyaz listedeki tekil filtreler indexlenir (bunlar landing page olacak),
  - Çoklu kombinasyonlara `<meta name="robots" content="noindex,follow">`,
  - `robots.txt`'e crawl kısıtı.

### 1.4 BreadcrumbList
Sitede hiç breadcrumb yok — ne görsel ne schema. Eklenecek:
`Ana Sayfa › Yurtdışı Turları › Dubai Turları › Dubai 4 Gece Turu`
Hem `BreadcrumbList` JSON-LD hem görünür navigasyon. SERP'te URL yerine breadcrumb
gösterilir, tıklama oranını belirgin artırır. Ayrıca iç link ağını güçlendirir.

### 1.5 robots.txt
Mevcut dosyada `Sitemap: /sitemap.xml` var — spec mutlak URL şart koşar, bu satırı Google
yok sayıyor. Ayrıca yeni engellenmesi gerekenler: `/yapay-zeka-arama`, `/favorilerim`,
`/profilim`, `/bildirimler`, `/kuponlarim`, `/turlar/karsilastir`, filtre kombinasyonları.

### 1.6 Sitemap yeniden yazımı
[SitemapController.php](app/Http/Controllers/SitemapController.php) tek dosya üretiyor. Yenisi:
- `sitemap.xml` → **index**, altında `sitemap-turlar.xml`, `sitemap-kategoriler.xml`, `sitemap-destinasyonlar.xml`, `sitemap-acentalar.xml`, `sitemap-blog.xml`, `sitemap-sayfalar.xml`
- Acenta sayfaları ve kurumsal/legal sayfalar şu an sitemap'te **hiç yok** — eklenecek
- Statik girdilere `lastmod`
- Görsel sitemap (`image:image`) — Google Görseller'den trafik
- 50.000 URL sınırına karşı otomatik bölme
- `Post::latest()->get()` → 0 kayıt olsa da tüm tabloyu belleğe çekiyor; `chunk` ile akış

### 1.7 Altyapı
- `APP_URL` şu an bir Cloudflare tüneli (`implied-drink-adjust-helps.trycloudflare.com`) — canlı domaine sabitlenecek
- `forceScheme('https')` + `TrustProxies` (Plesk arkasında karışık içerik / http canonical riski)
- www ↔ kök domain arasında tek yönlü 301
- Sondaki `/` için tek kural
- 404 sayfası doğru HTTP kodu döndürüyor mu (soft 404 kontrolü)

---

## FAZ 2 — Rich result (SERP'te öne çıkma)

### 2.1 Product schema'yı tamamla
Şu an eksik: `aggregateRating`, `review`, `priceValidUntil`, `sku`, `itemCondition`,
`hasMerchantReturnPolicy`, `shippingDetails`. Fiyat aralığı olan turlarda tekil `price`
yerine `AggregateOffer` (`lowPrice`/`highPrice`) doğru olan.

> ⚠️ **Dürüst uyarı:** `aggregateRating` sadece **gerçek** yorum varken basılabilir.
> Şu an toplam 1 yorum var. Yorum olmadan puan basmak Google'ın yapısal veri spam
> politikasının ihlali ve manuel işlem (manual action) sebebidir — sitenin tamamı
> aramadan düşer. Bu yüzden 2.1'in yorum kısmı, 4.3'teki yorum toplama işi
> yürüyene kadar **kapalı kalır**. Diğer alanlar hemen eklenebilir.

### 2.2 Yeni schema tipleri
| Sayfa | Schema | Kazanç |
|---|---|---|
| Tur | `TouristTrip` + `Product` | Turizme özel, Google seyahat sonuçlarına girer |
| Tur | `FAQPage` (sık sorulanlar) | SERP'te açılır soru blokları |
| Kategori/destinasyon | `ItemList` | Liste sonucu görünümü |
| Destinasyon | `TouristDestination` | Yer bazlı sonuçlar |
| Acenta | `TravelAgency` + `LocalBusiness` | Harita/işletme sonuçları |
| Kurumsal | `Organization` (tam: adres, telefon, sosyal) | Bilgi paneli (knowledge panel) |
| Blog | `Article` + `author` | Haber/makale görünümü |

### 2.3 Başlık ve açıklama şablonları
Şu an tur başlığı `$tour->title . ' — turXtur'`. Formüle bağlanacak:
`{Tur adı} Fiyatları {Yıl} — {N} Acenta Karşılaştırmalı | turXtur`
Meta description'a fiyat + acenta sayısı + kalkış bilgisi (tıklama oranını artıran veriler).
55–60 / 150–155 karakter sınırları otomatik denetlenecek.

---

## FAZ 3 — Kapılı programatik sayfalar ⭐ trafiğin ana kaynağı

Kural: **bir sayfa ancak `≥3` gerçek turu varsa yayına girer.** Eşiğin altındakiler
üretilmez, sitemap'e girmez, `noindex` alır. Envanter büyüdükçe sayfa kendiliğinden açılır.

### 3.1 Sayfa aileleri
| Kalıp | Örnek URL | Bugün açılacak sayfa |
|---|---|---|
| Destinasyon turları | `/dubai-turlari` | ~8 (Dubai 9, İstanbul 7, Antalya 7, Fethiye 5, Bodrum 4, Kapadokya 4, Trabzon 3…) |
| Kategori turları | `/avrupa-sehirleri-turlari` | ~2 |
| Kategori × destinasyon | `/kapadokya-kultur-turlari` | 0 → envanterle açılır |
| Kalkışlı | `/istanbul-kalkisli-kapadokya-turlari` | 0 → **Faz 0.2 sonrası** açılır |
| Süre | `/2-gece-3-gun-turlar` | envantere göre |
| Fiyat | `/ucuz-turlar`, `/10000-tl-alti-turlar` | envantere göre |
| Tema | `/balayi-turlari`, `/hafta-sonu-turlari` | envantere göre |
| Karşılaştırma ⭐ | `/dubai-turu-fiyat-karsilastirma` | **asıl fırsat — rakip yapamıyor** |

### 3.2 Her landing page'de zorunlu olanlar
Boş kabuk sayfa üretmemek için her sayfada:
- **Özgün açıklama metni** (≥300 kelime) — şablon değil. Mevcut `DestinationProfile` ve
  `KnowledgeService` altyapısı ([project_destination_knowledge](.)) bunu üretebilir.
- Gerçek veriden gelen tablo: en düşük/ortalama fiyat, acenta sayısı, süre dağılımı, en iyi sezon
- Fiyat karşılaştırma tablosu (acenta × fiyat) — **bu sayfanın rakipte olmayan kısmı**
- O sayfaya özgü SSS + `FAQPage` schema
- İlgili sayfalara iç linkler (silo yapısı)
- `ItemList` + `BreadcrumbList` schema

### 3.3 İç link mimarisi
Şu an turlar arası bağlantı zayıf. Kurulacak:
`Ana sayfa → Kategori → Alt kategori → Destinasyon → Tur` piramidi, her tur sayfasında
"Benzer turlar" / "Aynı destinasyonda" / "Aynı bütçede" blokları. Her sayfa ana sayfadan
en fazla 3 tıklama uzakta olmalı.

---

## FAZ 4 — İçerik ve otorite (uzun vadeli, hedefin asıl belirleyicisi)

| # | İş | Not |
|---|---|---|
| 4.1 | **Envanter büyütmesi: 92 → 1.000+ tur** | SEO'nun tek en güçlü kaldıracı. Acenta kazanımı = SEO işi |
| 4.2 | Blog: 0 → haftada 2 yazı | "kapadokya'da ne yenir", "dubai vize", "balayı için en iyi 10 rota" — bilgi aramalarını yakala, tur sayfalarına link ver |
| 4.3 | Gerçek yorum toplama akışı | Satış sonrası e-posta + tur sayfasında yorum çağrısı. `aggregateRating`'in ön koşulu. **Sahte yorum kesinlikle yok — manuel işlem riski** |
| 4.4 | Google Business Profile | Marka sorgularında bilgi paneli |
| 4.5 | Backlink: turizm blogları, acenta siteleri, yerel basın, dizinler | Otoritenin tek gerçek kaynağı. Satın alınan link = ceza |
| 4.6 | E-E-A-T sinyalleri | Hakkımızda, ekip, künye, iletişim, editoryal politika. YMYL'ye yakın alan — Google güven arıyor |

---

## FAZ 5 — Hız (Core Web Vitals, sıralama faktörü)

- [app.blade.php](resources/views/layouts/app.blade.php) tek başına **150 KB** — kritik CSS ayrılıp geri kalanı ertelenecek
- İki ayrı Google Fonts isteği render-blocking → self-host + `font-display:swap` + preload
- **28 `<img>` etiketinin 21'inde `alt` yok** — hem Google Görseller trafiği hem erişilebilirlik kaybı
- Tüm sitede sadece **2** `loading="lazy"` — ekran altı görsellerin tamamına eklenecek
- WebP/AVIF dönüşümü + `srcset` (responsive görseller)
- Hero görseline `fetchpriority="high"` (LCP)
- Görsellere `width`/`height` (CLS)
- Liste sorgularında N+1 denetimi
- Redis sayfa cache'i (mevcut altyapı var)

---

## Ölçüm

| Araç | Amaç |
|---|---|
| Google Search Console | **İlk gün kurulmalı** — veri geçmişe dönük gelmiyor, geciken her gün kalıcı kayıp |
| Bing Webmaster Tools | Bing + ChatGPT aramasını besliyor |
| Yandex Webmaster | Türkiye'de azımsanmayacak pay |
| GA4 | Organik dönüşüm takibi |
| Haftalık pozisyon takibi | Hedef anahtar kelime seti |

---

## Sıra ve beklenti

| Faz | İş | Sonuç ne zaman görünür |
|---|---|---|
| Faz 1 (teknik) | Kod | 2–4 hafta içinde index iyileşmesi |
| Faz 0 (veri) | Kod + içerik | Faz 1 ile paralel |
| Faz 2 (schema) | Kod | 2–6 hafta, rich result |
| Faz 3 (landing) | Kod + içerik | 1–3 ay |
| Faz 5 (hız) | Kod | Anında ölçülür, sıralamaya etkisi kademeli |
| Faz 4 (otorite) | Sürekli | **6–12 ay** |

**Dürüst beklenti:** Faz 1+2+3+5 tamamlandığında uzun kuyruk ve karşılaştırma
sorgularında ilk sayfa, çoğunda ilk 3 gerçekçi. "yurtdışı turları" gibi head
term'lerde 1. sıra, Faz 4.1 ile envanter 1.000+ olmadan gerçekçi değil — orada
rakibin 20.000 turu ve 15 yıllık domain otoritesi var; bu teknik bir eksiklik değil,
ölçek farkı. Hiçbir teknik SEO çalışması bunu tek başına kapatmaz, ama teknik taraf
eksikken ölçek de kapatmaz.

---

## Açık kalan

- **Canlı domain adı** — canonical, sitemap, robots ve Search Console için gerekli
- Faz 0.5/0.6 için: mevcut 92 tur `tour_url` üzerinden yeniden zenginleştirilebilir mi (acenta siteleri hâlâ ayakta mı)
