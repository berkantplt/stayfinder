# turXtur SEO Planı

> Hazırlanma tarihi: 2026-08-11
> Hedef: "her kategoride, her şehirde, her turda aramalarda en üstte olmak"

---

## 🔴 CANLI ÖLÇÜM (turxtur.com, 2026-08-15)

Domain doğrulandı: **turxtur.com** (195.155.140.91), `APP_ENV=production` doğru,
robots.txt ve sitemap yayında.

### Canlı envanter — planın dayandığı varsayımı değiştiriyor

| Ölçüm | Yerel (demo) | **CANLI** |
|---|---|---|
| Tur | 92 | **38** |
| Acenta | 12 | **1** (tatilone) |
| Destinasyon | 10 | **0** |
| Aktif kategori | 35 | **0** |
| Blog | 0 | **0** |

> ⚠️ **En kritik bulgu: canlıda 1 acenta var.**
>
> Planın tamamı "12 acentanın fiyatını yan yana gösterebilen tek siteyiz"
> üzerine kuruluydu. Rakip araştırması bu boşluğun yapısal olduğunu doğruladı
> (MNG kendi sayfasında rakip adını 0 kez yazıyor). Ama **tek acentayla o
> tablo kurulamaz** — `LandingStats` zaten `acenta > 1` şartıyla basıyor,
> yani canlıda karşılaştırma bölümü hiç görünmez.
>
> Sonuç: **SEO'nun önündeki asıl darboğaz acenta kazanımı.** Teknik taraf
> hazır ve bekliyor; ikinci acenta geldiği gün karşılaştırma sayfaları
> kendiliğinden anlam kazanır.

Destinasyon ve kategori sayısının 0 olması landing sayfalarını da boşa
düşürüyor: `/kapadokya-turlari` üretilemez çünkü canlıda "Kapadokya"
destinasyonu tanımlı değil.

### Canlıda ne var, ne yok

| | Durum |
|---|---|
| robots.txt (rota tabanlı, `/acenta$` düzeltmesiyle) | ✅ canlı |
| sitemap index + 5 bölüm | ✅ canlı |
| Slug URL'leri + 301 | ✅ canlı (rota) |
| BreadcrumbList | ✅ canlı |
| Product schema | ✅ canlı (eski tekil biçim) |
| `sitemap-kategoriler.xml` | ❌ 404 |
| Landing sayfaları (`/kapadokya-turlari`) | ❌ 404 |
| Admin kalkış şehirleri ekranı | ❌ 404 |
| TouristTrip + FAQPage + subTrip | ❌ yok |
| Başlık/H1 formülleri | ❌ yok (`Turlar — turXtur`, H1 sayaçsız) |
| `seo:backfill-tour-slugs` çalıştırıldı mı | ❌ hayır (`kapadokya-turu-wDBRf`) |

**Yani canlıda Faz 1 var, Faz 2 / 0.2 / 3 yok.**

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

## FAZ 0.2 — ✅ ARAÇLAR HAZIR, VERİ GİRİŞİ BEKLİYOR

652 test geçiyor (+`tests/Feature/DepartureCityExtractorTest.php`, 26 test).

| Araç | Dosya |
|---|---|
| Otomatik çıkarım (4 kaynak) | `app/Support/DepartureCityExtractor.php` |
| Toplu doldurma komutu | `php artisan seo:backfill-departure-city --dry-run` |
| Admin toplu düzenleme ekranı | `/admin/kalkis-sehirleri` |

### Çıkarım kaynakları (güvenilirlikten zayıfa)

1. `departure_points` — acentanın girdiği yapısal alan (`"21:00 Ankara AŞTİ"`)
2. Başlık — `"İstanbul Kalkışlı…"`, `"İzmir Çıkışlı…"`, `"Antalya'dan Günübirlik"`
3. Programın ilk günü — `"1. Gün: Konya kalkışlı hareket"`
4. Açıklama — yalnız açık kalkış kalıbı varsa

### ⚠️ Ölçüm sonucu: otomasyon bu veriyi kurtaramıyor

Yerel veritabanında **93 turun yalnız 2'sinde** kalkış sinyali var:

```
Bulundu: 2  |  Kaynak bulunamadı: 91
Kaynak dağılımı: title=2
```

Sebep, daha önce not edilen demo veri sorunu: `departure_points` **0 turda**
dolu, `stop_cities` **0 turda** dolu, açıklamalar şablon
(*"Bu muhteşem tur ile X keşfedin"*), `tour_url`'lerin tamamı jollytur.com'a
işaret ediyor. Yani çıkarılacak bir şey yok — komutun hatası değil.

**Komut bilerek tahmin etmiyor.** Destinasyondan kalkış şehri TÜRETİLMEZ:
"Kapadokya turu" İstanbul'dan da Ankara'dan da kalkabilir. Uydurulan değer
"İstanbul kalkışlı Kapadokya turları" sayfasına İzmir'den kalkan turu koyar —
kullanıcı sayfaya güvenip tıklar, yanlış ürünle karşılaşır. Boş alan bundan iyidir.

### Sıradaki adım (insan işi)

1. **Canlıda ölç:** `php artisan seo:backfill-departure-city --dry-run`
2. Otomatik bulunanları uygula: `php artisan seo:backfill-departure-city`
3. Kalanı `/admin/kalkis-sehirleri` ekranından elle gir — sayfa başına 50 tur,
   otomatik öneri hazır seçili gelir, tek kaydetmede yazılır.
4. Eksik sayısı düşünce **Faz 3'ün şehir kalkışlı matrisi** açılabilir.

> Not: Doldurulmayan tur yalnız kalkış sayfalarında görünmez; başka hiçbir
> yerde davranış değişmez (`scopeDepartsFrom` şehir bilgisi olmayanı gizler).

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

## FAZ 2 — ✅ TAMAMLANDI (puan/yıldız hariç)

618 test geçiyor (+`tests/Feature/TourSchemaTest.php`, 24 test).

| İş | Dosya | Rakipte durum |
|---|---|---|
| `TouristTrip` + `Product` + `FAQPage` tek `@graph` | `app/Support/TourSchema.php` | tatilsepeti/jolly **yanlış tip** (LodgingBusiness) |
| Günlük program → `subTrip[]` | `TourSchema::subTrips()` | 6 siteden 4'ünde **hiç yok** |
| `FAQPage` + **görünür** SSS bloğu | `TourSchema::faq()` + `tours/show.blade.php` | 6 sitenin **hiçbirinde yok** |
| `AggregateOffer` (tarih bazlı fiyat aralığı) | `TourSchema::offer()` | kimsede yok |
| `priceValidUntil`, `seller`, `availability` | aynı | eksik |
| ISO 4217 para birimi doğrulaması | `TourSchema::ISO_CURRENCIES` | **setur `"TL"` yazıp geçersiz kılmış** |
| Başlık formülü + otomatik yıl | `Seo::listingTitle()`, `Seo::year()` | **SSC hâlâ elle yazılmış "2025" taşıyor** |
| H1 + envanter sayacı | destinasyon & tur listesi | tatilbudur kalıbı |
| `ItemList` → `TouristTrip` + `provider` | `TourSchema::itemList()` | **jollytur ve Prontotour'da hiç yok** |

### Uygulama sırasında çıkan iki hata

1. **Tüm site İngilizce tarih basıyormuş.** `APP_LOCALE=en` olduğu için Carbon
   "20 May 2026" ve "2 days ago" üretiyordu — 15 yerde, ve SSS üzerinden
   yapısal veriye de sızıyordu. `Carbon::setLocale('tr')` ile düzeltildi;
   uygulama locale'i değiştirilmedi (lang/ klasörü yok, `APP_LOCALE=tr` yapmak
   doğrulama mesajlarını Türkçeleştirmezken davranış değiştirirdi).
2. **"Kültür Turları Turları".** Kategori adları zaten "Turları" ekini taşıyor,
   naif ekleme başlığı bozuyordu. `Seo::stem()` ile ad gövdesine indirgeniyor.

### Bilerek yapılmayanlar

- **`aggregateRating` / `review` basılmıyor.** Sektörde kimse basmıyor ve ilk
  basan olma imkanı duruyor — ama gerçek yorum şart. Elimizde 1 yorum var;
  Faz 4.3 (yorum toplama) yürümeden açılmaz. Sahte puan = manuel işlem.
- **`FAQPage` görünür blok olmadan basılmaz.** Google şemanın sayfada görünür
  karşılığı olmasını şart koşar; ikisi de aynı kaynaktan (`TourSchema::faq`)
  üretiliyor, testle eşitlikleri kilitli.
- **Veri yoksa alan hiç basılmaz.** tatilbudur `description:""` ve `image:[""]`
  basıyor; boş alan, yanlış alandan iyidir değil — hiç alan olmamalı.

---

## FAZ 2 — özgün plan (referans için)

### 2.0 ⭐ Tur detay sayfası — sektörün tamamen boş bıraktığı alan

6 rakip tur sayfası incelendi: **hiçbirinde `aggregateRating`, `review` veya
`FAQPage` yok.** Bu, Faz 2'yi ilk plandakinden çok daha değerli yapıyor.

**Hemen yapılabilecekler (yorum gerektirmez):**

| İş | Neden |
|---|---|
| `TouristTrip` + `subTrip[]` — her gün ayrı `Trip` nesnesi | Sadece tatilbudur yapıyor; bizim `itinerary` alanımız bunun için hazır |
| `offers`: `priceValidUntil`, `availability`, `seller` (acenta), `itemCondition` | Kimsede tam değil |
| `priceCurrency` **ISO 4217** (`TRY`) | setur `"TL"` yazıp geçersiz kılmış |
| `FAQPage` — "Balon turu dahil mi?", "Vize gerekli mi?", "Çocuk indirimi var mı?" | **6 sitenin hiçbirinde yok** |
| `touristType` (`Aile`, `Çift`, `Arkadaş Grubu`) | Sadece tatilbudur'da |
| Self-canonical | **tatilsepeti tur sayfalarını kategoriye canonical veriyor** — kendi sayfalarını yarıştan çekiyor |
| Süresi dolan tura **301** (404 değil) | MNG 404'e düşürüp link değerini çöpe atıyor |

**`aggregateRating` — kilitli, ama ödül büyük.** Sektörde kimse yıldız basmıyor;
"kapadokya turu" SERP'inde yıldız gösteren ilk site olma imkanı açık. Ama bu
**gerçek yorum** ister ve elimizde 1 tane var. Faz 4.3 (yorum toplama) yürümeden
açılmaz — sahte puan basmak manuel işlem sebebidir.

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

### 2.3 Başlık ve açıklama şablonları (rakip ölçümüyle kalibre edildi)

Ölçülen rakip title'ları: 40–63 karakter bandında, 8'den 6'sında **yıl damgası** var,
"Fiyatları" kelimesi neredeyse zorunlu, **rakam yok**.

| Sayfa | Formül |
|---|---|
| Tur | `{Tur adı} Fiyatları {YIL} — {N} Acenta Karşılaştırmalı \| turXtur` |
| Destinasyon | `{Ad} Turları \| {Ad} Turu Fiyatları {YIL} — turXtur` |
| Kategori | `{Ad} \| {Ad} Fiyatları {YIL} — turXtur` |

> ⚠️ **Yıl değişkenden gelmeli, elle yazılmamalı.** SSC Tur'un
> `/ankara-cikisli-karadeniz-turlari` sayfası hâlâ "2025" taşıyor — elle yazılan
> yıl damgası her ocak ayında sessizce bayatlıyor.

**H1'e envanter sayısı:** `Kapadokya Turları ( 12 )`. Tatilbudur bu kalıpla üç
kategori sorgusunda birden 1. sırada; hem tazelik hem envanter derinliği sinyali.
Bizde tur sayacı zaten var, tek satırlık iş.

### 2.4 Kategori/destinasyon sayfalarına `ItemList`

Rakip taramasının en net boşluğu: **jollytur ve Prontotour'da ürün yapısal verisi
hiç yok.** tatilsepeti 30, tatilbudur 20 `TouristTrip` basıyor. Bizim eklememiz
gereken, onlarda olmayan alan: her ürüne **`Organization` (turu satan acenta)** —
pazaryeri olduğumuz için doğal olarak bizde var.

> Tatilbudur'un hatasını yapmayın: her `TouristTrip.description` alanına kategori
> meta description'ını kopyalamış (20 üründe aynı metin). tatilsepeti ise turun
> gerçek rotasını yazıyor: `"Akyaka - Azmakçayı - Gökova - Sedir Adası - ..."`.

---

## 🔍 RAKİP SERP ARAŞTIRMASI (2026-08-13)

İki agent, hedef sorgularda üst sıradaki sayfaların ham HTML'ini çekip yapılarını
çözdü. İncelenen siteler: **tatilsepeti, tatilbudur, jollytur, gruppal, MNG Turizm,
Prontotour, Touristica, SSC Tur, Setur**.

> ⚠️ **Ölçüm sınırı:** kullanılan arama aracı ABD indeksinden çalışıyor; sıra
> numaraları `google.com.tr` ile birebir aynı olmayabilir. **Sayfa yapıları
> gerçek** (ham HTML parse edildi), sıralamalar yaklaşıktır.

### Ölçülen rakamlar

| Site | Kategori/destinasyon sayfası özgün metin | SSS | ItemList schema | URL kalıbı |
|---|---|---|---|---|
| tatilsepeti (Kapadokya) | **~2.560 kelime** | 5 görünür / 2 schema ❌ | ✅ 30 TouristTrip | `/kapadokya-turlari` |
| tatilbudur (Dubai) | ~1.980 kelime | yok | ✅ 20 TouristTrip | `/yurtdisi-turlar/dubai-turlari` |
| tatilsepeti (Yunan Adaları) | ~2.048 kelime | 11 görünür / **schema yok** ❌ | ✅ 30 | `/yunan-adalari-turlari` |
| gruppal (gemi) | ~1.515 kelime | ✅ 7 + FAQPage | ❌ | `/gemi-turlari` |
| jollytur (İtalya) | ~1.090 kelime | ✅ 5 FAQPage | **❌ yok** | `/italya-turlari` |
| jollytur (Antalya) | ~395 kelime | yok | **❌ yok** | `/antalya-turlari` |
| tatilbudur (Bodrum) | **0 kelime** | yok | ✅ 20 | `/kultur-turlari/bodrum-turlari` |

### Planı değiştiren 5 bulgu

**1. Hiçbiri query string kullanmıyor.** Kategori ve destinasyon sayfalarının
tamamı düz yol segmenti: `/kapadokya-turlari`, `/gemi-turlari`, `/doga-turlari`.
Gruppal ve MNG sayfalarında **sıfır** query-string iç linki var. Filtreler
URL'e hiç yansımıyor (client-side `<select>`); onun yerine **elle kurulmuş düz
kategori sayfaları** var. → Bizim `/turlar?category=x` kalıbımız yanlış katman.

**2. Şehir × destinasyon matrisini büyükler BOŞ BIRAKMIŞ.** Bağımsız doğrulandı:

| URL | Kod |
|---|---|
| `tatilsepeti.com/istanbul-cikisli-kapadokya-turlari` | **404** |
| `jollytur.com/istanbul-cikisli-kapadokya-turlari` | **404** |
| `ssc.com.tr/ankara-cikisli-kapadokya-turlari` | **200** |

Bu sorguları orta ölçekli acenteler (SSC, Touristica) `{şehir}-cikisli-{dest}-turlari`
matrisiyle kapıyor ve 1. sırada çıkıyorlar. **Pazaryerinin girmediği, envanteri
bizde olan bir boşluk.**

**3. Rekabetin bar'ı uzun kuyrukta çok düşük.** Touristica
`/istanbul-cikisli-kapadokya-turlari` sayfasında **0 tur, H1 yok, metin yok,
ürün schema'sı yok** — buna rağmen sorguda 1. sırada. Tam eşleşen URL + tam
eşleşen title tek başına baskın sinyal. Gerçek envanter + 300-500 kelime metinle
bu sayfalar kolayca geçilir.

**4. jollytur ve Prontotour'da `ItemList`/`TouristTrip` schema HİÇ YOK.** Antalya,
Trabzon, İtalya sayfalarında ürün yapısal verisi yok. Doğrudan fark yaratılabilir alan.

**5. Herkes SSS'yi yarım yapmış.** tatilsepeti Kapadokya'da 5 görünür soru var,
schema'da 2; Yunan Adaları'nda 11 görünür soru var, `FAQPage` schema **hiç yok**;
SSC'nin `ItemList` bloğu **bozuk JSON**. Eksiksiz uygulamak ucuz bir üstünlük.

### TUR DETAY SAYFALARI — 6 site incelendi

tatilsepeti, tatilbudur, jollytur, setur, gruppal, MNG Turizm tur detay sayfaları
ham HTML olarak çekildi.

| Site | URL kalıbı | title (krkt) | Schema | aggregateRating | Yorum |
|---|---|---|---|---|---|
| tatilsepeti | slug + `-tr-{ID}` | 14 ❌ | BreadcrumbList, **LodgingBusiness** ❌ | ❌ | 0 |
| tatilbudur | saf slug (yıl gömülü) | 42 | BreadcrumbList, **TouristTrip+subTrip**, Product | ❌ | 0 |
| jollytur | saf slug | 58 | **bozuk JSON** ❌ | ❌ | 0 |
| setur | saf slug | 56 | TouristTrip + itinerary | ❌ | 0 |
| gruppal | saf slug | 40 | Organization, BreadcrumbList | ❌ | 0 |
| MNG | slug + `-{ID}` | 77 ❌ | sadece BreadcrumbList | ❌ | 0 |

**🎯 En büyük bulgu: 6 sitenin HİÇBİRİNDE `aggregateRating`, `review` veya
`FAQPage` yok. Hiçbirinde tek bir kullanıcı yorumu yok.** Kendi doğrulamam:

```
tatilsepeti /kapadokya-turu-tr-169688     aggregateRating geçiş: 0
setur       /kapadokya-turu-2-gece-3-gun  aggregateRating geçiş: 0
tatilbudur  /sofya-plovdiv-turu-2026      aggregateRating geçiş: 0
```

Yani "kapadokya turu" SERP'inde **yıldız gösteren tek site olma imkanı açık duruyor.**

**Rakiplerin teknik hataları (bizim yapmamamız gerekenler):**

- **tatilsepeti tur sayfalarını kendi eliyle indeksten düşürüyor.** Canonical turun
  kendisine değil **kategori sayfasına** işaret ediyor — kendi curl'ümle doğruladım:
  `/kapadokya-turu-tr-169688` → `canonical: /kapadokya-turlari`. Binlerce tur
  sayfasının değeri birkaç kategori sayfasına toplanıyor, tur sayfaları sorgu
  yarışına hiç girmiyor.
- **jollytur'da H1 yok**, tur adı `<h3>` içinde. JSON-LD'si fazladan `;` yüzünden
  parse edilmiyor, breadcrumb URL'inde slash eksik (`jollytur.comkapadokya-turlari`).
- **Yanlış schema tipi:** tatilsepeti ve jolly bir **tura** `LodgingBusiness`
  (konaklama işletmesi) basıyor. Jolly'nin `priceRange` değeri sayı bile değil:
  `"Çok Uygun Fiyatlar ve Taksit Seçenekleri"`.
- **setur'un `priceCurrency` değeri `"TL"`** — ISO 4217 geçersiz, `"TRY"` olmalı.
- **MNG süresi dolan turları 404'e düşürüyor, 301 vermiyor** (3 URL doğrulandı).

**Günlük program hiçbirinde semantik değil.** Altı sitenin dördü programı sadece
düz div/accordion olarak basıyor; yalnız tatilbudur (`subTrip[]` → her gün bir
`Trip`) ve setur (`itinerary[]`) schema'ya taşıyor. → **Bizim `itinerary` alanımız
%100 boş; doldurulduğunda bu bir eksik kapatma değil, doğrudan üstünlük olur.**

---

### KARŞILAŞTIRMA ALANI — boş mu? Evet, ve boşluk yapısal

16 sorgu tarandı ("kapadokya turu fiyatları", "en ucuz X turu", "tur karşılaştırma",
"hangi acenta daha ucuz", "jolly tur mu ets tur mu"…).

**Fiyat/ucuzluk sorgularının 18 sonucundan 18'i tek-acenta satış sayfası.** Ölçüm:
MNG'nin "Ekonomik Turlar" sayfasında MNG adı 40+ kez, **rakip adı 0 kez**.
Jolly'nin sayfasında jolly 280+ kez, **rakip 0 kez**.

**Denemiş ve çıkmış oyuncular:**

| Site | Durum |
|---|---|
| **turzz.com** (2021'de "tur karşılaştırma" olarak lanse) | 308 ile **turzzai.com**'a yönleniyor — artık acentalara WhatsApp chatbot satan B2B SaaS |
| **seyahatim.com** | DNS SERVFAIL — ölü |
| **enuygun.com** (TR'nin en büyük seyahat metasearch'ü) | Uçak/otobüs/otel/araç var, **paket tur dikeyi YOK** — sayfasında "paket tur" 0 kez geçiyor |

> ⚠️ **Dikkat edilmesi gereken tek oyuncu: turafix.com**
> Bizimkiyle **birebir aynı** konumlandırma: *"Türkiye'nin ilk ve en kapsamlı paket
> tur kıyaslama platformu. Biz satmıyoruz, sadece kıyaslıyoruz."* Partner iddiası:
> ETS, Pronto, Jolly, AFM, Lüks Seyahat, Tatil Budur.
>
> Ama envanteri yok. Kendi doğrulamam: ana sayfa 200, **sitemap'te 37 URL**
> (10 Avrupa şehri × 2 statik sayfa). Alan adı ~9 aylık (Kasım 2025). Inline
> script'lerinde `Jolly`=0, `ETS`=0, `fiyat`=0 geçiyor; tur API'si yok. Paris
> sayfasında gerçek fiyat yok, sadece `"Ortalama Fiyat: 25.000–45.000 ₺"` bandı.
> Sponsor kartları boş: *"Acente Yeriniz — Bu alan sponsorlu kart için açık"*.
> 16 sorgunun **hiçbirinde** ilk 3'e girmedi.
>
> **Sonuç: söylemi kurmuş, envanteri kurmamış bir kabuk.** Bizim 12 acentalık
> canlı fiyat verimiz varken içerik derinliği farkı çok büyük — ama kategori adını
> kapmadan önce hareket etmek gerekiyor.

**Boşluğun yapısal sebebi:** acentalar rakip fiyatı göstermek *istemez* (0 rakip
mention ölçümü bunu kanıtlıyor), Enuygun tur dikeyine girmemiş, deneyen tek oyuncu
B2B'ye kaçmış. Yani bu "kimse akıl etmedi" boşluğu değil, **"envanter toplamak zor"
boşluğu** — ve envanter tam olarak bizim savunma hendeğimiz.

**Sahipsiz alan: karar sorguları.** `"jolly tur mu ets tur mu"` sorgusunun ilk 3'ü
kizlarsoruyor, Yandex yacevap ve tek bir şikayet metni. Milyonluk markalar arası
kararı forum yorumları veriyor. Elimizdeki gerçek fiyat verisiyle *"Jolly vs ETS:
aynı 15 destinasyonda fiyat farkı"* tipi veri-tabanlı sayfalar üretilebilir.

---

### Kopyalanacak somut kalıplar

- **Title:** `{Ad} Turları | {Ad} Turu Fiyatları {YIL} — turXtur`, 42–61 karakter.
  8 sayfadan 6'sında yıl damgası var. **Yıl değişkenden gelmeli** — SSC hâlâ elle
  yazdığı "2025"i taşıyor.
- **H1:** tam anahtar kelime + parantez içinde canlı envanter sayısı —
  `Bodrum Turları ( 28 )` (tatilbudur, üç sorguda birden 1.).
- **Şablon H2 iskeleti** (tatilbudur Dubai ve Kapadokya'da **birebir aynı 11 başlık**):
  `{D} Turu` · `{D} Turu Rotaları` · `{D} Turu Fiyatları` · `{D} Turları Kaç Gün Sürer?` ·
  `Ne Zaman Tercih Edilmeli?` · `Neleri Kapsar?` · `{D}'de Gezilecek Yerler` ·
  `Konaklama` · `Yeme İçme` · `Nasıl Gidilir?` · `Kimler İçin Uygun?` (+ yurtdışıysa `Vize Bilgisi`)
- **Her landmark'a ayrı H3 + 30-45 kelime** (Burj Khalifa, Peri Bacaları…) —
  "kapadokya gezilecek yerler" bilgi sorgularını da yakalıyor.
- **Sayfalama (tatilsepeti kalıbı):** 30 ürün/sayfa, `?sayfa=N`, iç sayfada
  **self-canonical**, `rel=prev/next`, title'a `- sayfa 2`, ve **uzun SEO metni
  sadece 1. sayfada** (2. sayfada tamamen kaldırılıyor).
- **Envanteri sıfırlanan sayfa öldürülmüyor.** Gruppal `/kayak-turlari` Ağustos'ta
  0 ürün listeliyor ama H1, 523 kelime metin, 7 soruluk FAQPage, breadcrumb duruyor.
  404/noindex yapılmıyor.
- **İç link hacmi:** kategori sayfası başına 100–470 benzersiz iç link. Kırılım
  eksenleri: destinasyon, bölge, **marka/tedarikçi** (bizde = acenta), takvim
  (`/yilbasi-turlari`, `/29-ekim-turlari`), kitle (`/aile-tatilleri`), kısıt (`/vizesiz-turlar`).

---

## FAZ 3 (1/2) — ✅ DÜZ LANDING ADRESLERİ YAYINDA

669 test geçiyor (+`tests/Feature/LandingPageTest.php`, 13 test).

| İş | Dosya |
|---|---|
| Slug normalizasyonu (hepsi `-turlari` ile biter) | `app/Support/LandingSlug.php` |
| Landing sayfası (kategori + destinasyon) | `app/Http/Controllers/LandingController.php`, `resources/views/landing/show.blade.php` |
| Rota (kök seviyede, ek kısıtlı, dosyanın en sonunda) | `routes/web.php` |
| Eski adreslerden 301 | `DestinationController`, `TourController::canonicalLandingRedirect` |
| Sitemap'e `kategoriler` bölümü | `SitemapController` |
| İç linkler düz adrese çevrildi | `MegaMenu`, `home.blade.php`, `tours/show.blade.php` |

**45 yeni landing adresi** (35 kategori + 10 destinasyon), sitemap 124 → **159 URL**.

### 301 haritası

| Eski | Yeni |
|---|---|
| `/destinasyonlar/kapadokya` | `/kapadokya-turlari` |
| `/turlar?category=kultur-turlari` | `/kultur-turlari` |
| `/turlar?destination=Kapadokya` | `/kapadokya-turlari` |
| `/turlar?category=x&min_price=y` | **yönlendirilmez** — filtre, landing değil (noindex) |

### Uygulama sırasında düzeltilen çelişki

`noindex` alan sayfa BAŞKA bir adrese kanonik veriyordu. Google bu ikisini
çelişkili bulup ikisini birden yok sayabilir. Artık noindex sayfalar kendine
kanonik veriyor; yalnız izleme parametreleri temizleniyor.

### ✅ Veri-tabanlı içerik blokları (LLM'siz)

`app/Support/LandingStats.php` — her cümlenin arkasında canlı envanter var:
fiyat değişince metin de değişir, bayatlamaz, uydurmaz, **maliyeti yoktur.**

Üretilen H2 iskeleti (rakip kalıbının veriden gelebilen kısmı):

| Bölüm | İçerik |
|---|---|
| `{Ad} Tur Fiyatları` | min / ortanca / max — ortalama değil **medyan** (tek lüks tur ortalamayı şişiriyor) |
| `{Ad} Acenta Fiyat Karşılaştırması` ⭐ | acenta × tur sayısı × en düşük × en yüksek, en ucuza "EN UYGUN" rozeti |
| `{Ad} Kaç Gün Sürüyor?` | süre dağılımı, adetli |
| `{Ad} İçin Kalkış Ayları` | ay dağılımı, adetli |
| `Sıkça Sorulan Sorular` | aynı verilerden 4 soru + `FAQPage` şeması |

**Karşılaştırma tablosu sayfanın rakipte olmayan kısmı.** Ölçüm: MNG kendi
sayfasında MNG adını 40+ kez, rakip adını **0 kez**; Jolly 280+ kez kendi
adını, **0 kez** rakip adını yazıyor. Tek acentalı bir site bu tabloyu
yapısal olarak üretemez.

Sonuç: sayfa başına **~50 kelime → ~433 kelime**, sıfır LLM maliyeti,
her zaman güncel.

### ✅ Şehir profili blokları (mevcut veriden, yeni LLM çağrısı YOK)

`app/Support/LandingProfile.php` — kaynak: **zaten var olan** `DestinationProfile`
tablosu. 55 şehir için üretilmiş (summary, best_months, crowded_months,
climate_by_month, vibe_tags, crowd_score); landing sayfalarında hiç
kullanılmıyordu. Üretim maliyeti çoktan ödenmiş içerik boşta duruyordu.

| Bölüm | Kaynak alan |
|---|---|
| `{Ad} Nasıl Bir Yer?` | `summary` |
| `{Ad} Turlarına Ne Zaman Gidilir?` | `best_months` + `climate_by_month` + `crowded_months` |
| `{Ad} Turları Kimler İçin Uygun?` | `vibe_tags` + `crowd_score` |

Örnek çıktı (Kapadokya):
> *"Kapadokya için en uygun aylar Nisan, Mayıs, Haziran, Eylül ve Ekim. Bu
> dönemde ortalama sıcaklık 10–20°C. Temmuz ve Ağustos ayları en yoğun dönem;
> bu aylarda fiyatlar yükselir ve yerler erken dolar."*

Kategori sayfalarında aranmaz — "Kültür Turları" bir şehir değil.

### Landing sayfası H2 iskeleti — mevcut durum

| # | Başlık | Kaynak |
|---|---|---|
| 1 | `{Ad} Tur Fiyatları` | envanter (min/medyan/max) |
| 2 | `{Ad} Acenta Fiyat Karşılaştırması` ⭐ | envanter (acenta × fiyat) |
| 3 | `{Ad} Nasıl Bir Yer?` | DestinationProfile |
| 4 | `{Ad} Turlarına Ne Zaman Gidilir?` | DestinationProfile |
| 5 | `{Ad} Turları Kimler İçin Uygun?` | DestinationProfile |
| 6 | `{Ad} Kaç Gün Sürüyor?` | envanter |
| 7 | `{Ad} İçin Kalkış Ayları` | envanter |
| 8 | `Sıkça Sorulan Sorular` | envanter + `FAQPage` |

**~50 → 521 kelime, sıfır yeni LLM maliyeti.**

### ⚠️ Eksik kalan: editoryal içerik

Sayfa iskeleti hazır ve metin bloğu listenin ALTINDA render ediliyor
(tatilsepeti/MNG yerleşimi), ama içerik yok:

| | Durum |
|---|---|
| Açıklaması olan kategori | **13 / 35** |
| Açıklaması olan destinasyon | **0 / 10** |
| Hedef uzunluk (rakip ölçümü) | **1.800–2.500 kelime** |

Şu an bu sayfalar rakiplerin ~%10'u kadar içerik taşıyor. Sıralama için
şablon H2 iskeleti (11 başlık) doldurulmalı — Faz 3'ün asıl işi bu.

---

## FAZ 3 (2/2) — Programatik sayfalar ⭐ (rakip araştırmasına göre revize edildi)

### Revizyon 1 — URL mimarisi: query string değil, düz yol

Faz 1'de `/turlar?category=x` "indexlenebilir facet" olarak bırakılmıştı. Araştırma
bunun yanlış katman olduğunu gösterdi — rakiplerin hiçbiri kullanmıyor. Yeni hedef:

| Eski | Yeni |
|---|---|
| `/turlar?category=kultur-turlari` | `/kultur-turlari` |
| `/destinasyonlar/kapadokya` | `/kapadokya-turlari` |
| — | `/istanbul-cikisli-kapadokya-turlari` |

Eski adresler **301** ile yenilerine taşınır. `App\Support\Seo::INDEXABLE_FACETS`
listesi boşalır: artık hiçbir query-string kombinasyonu indexlenmez.

### Revizyon 2 — Eşik gevşiyor: `≥3 tur` → `≥1 tur + zorunlu içerik`

İlk plandaki `≥3` eşiği doorway-page riskine karşıydı. SERP kanıtı bar'ın çok daha
düşük olduğunu gösterdi (Touristica 0 turla 1. sırada). Doorway riski **turun
azlığından değil, içeriğin yokluğundan** doğar. Yeni kural:

> Sayfa açılır ⟺ (en az 1 aktif tur) **VE** (özgün metin + SSS + schema tam).

İçerik üretilemiyorsa sayfa açılmaz. Envanteri sıfırlanan sayfa **kapatılmaz** —
gruppal kalıbı: metin ve SSS kalır, liste "şu an tur yok + benzer sayfalar" olur.

### Revizyon 3 — Şehir kalkışlı matris öne alınıyor

Büyüklerin boş bıraktığı, bizim envanterimizin olduğu alan. **Ama tek girdisi
`departure_city` ve bu alan şu an tüm turlarda boş** → **Faz 0.2 kritik yola girdi.**
Faz 3'ün en yüksek getirili parçası ona bağlı.

### Sayfa aileleri (öncelik sırasıyla)

| # | Kalıp | Örnek | Neden bu sırada |
|---|---|---|---|
| 1 | `/{destinasyon}-turlari` | `/kapadokya-turlari` | Envanter hazır, kalıp kanıtlı |
| 2 | `/{kategori}` | `/kultur-turlari` | Envanter hazır (Faz 0.1 sonrası) |
| 3 | `/{sehir}-cikisli-{dest}-turlari` | `/istanbul-cikisli-kapadokya-turlari` | **Rakip boşluğu** — Faz 0.2'ye bağlı |
| 4 | `/{dest}-turu-fiyat-karsilastirma` | `/dubai-turu-fiyat-karsilastirma` | Yapısal üstünlük, rakip yapamaz |
| 5 | `/ucakli-{dest}-turlari` | `/ucakli-kapadokya-turlari` | Ulaşım kırılımı (tatilsepeti'de var) |
| 6 | Takvim | `/yilbasi-turlari`, `/29-ekim-turlari` | Mevsimsel hacim |
| 7 | Kısıt/kitle | `/vizesiz-turlar`, `/balayi-turlari` | Gruppal kalıbı |

### Her sayfada zorunlu içerik (araştırmadan kalibre edildi)

- **1.800–2.500 kelime** özgün metin, 10–13 bölüme dağıtılmış (bölüm başına 80–200 kelime)
- Şablon H2 iskeleti (yukarıdaki 11 başlık) — veritabanı alanlarına bağlanır
- Her landmark'a H3 + 30-45 kelime
- **Metin listenin ALTINDA** (tatilsepeti/MNG kalıbı) veya sol kolonda katlanabilir (gruppal)
- **5–11 SSS + `FAQPage` schema — görünür soru sayısı schema ile birebir eşit**
- `ItemList` → `TouristTrip` + `Offer` + `subjectOf: Event` + **`Organization` (acenta)**
- Fiyat karşılaştırma tablosu (acenta × fiyat) — **rakipte olmayan kısım**
- `BreadcrumbList` + 100+ iç link

### İç link mimarisi
`Ana sayfa → Kategori → Destinasyon → Şehir kalkışlı → Tur` piramidi. Her tur
sayfasında "Benzer turlar" / "Aynı destinasyonda" / "Aynı bütçede". Her sayfa ana
sayfadan en fazla 3 tıklama uzakta.

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
