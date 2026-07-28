# turXtur Chatbot v2 — Teknik Tasarım

**Durum:** tasarım, onay bekliyor. Kod yazılmadı.
**Bağlam:** v1 donduruldu (`f4f7cf4`, `AI_CHAT_ENABLED=false`). Bu belge v1'in yerine geçecek sistemin tasarımıdır.
**Altın senaryolar:** [`resources/eval/chatbot-v2-senaryolar.json`](resources/eval/chatbot-v2-senaryolar.json) (24 senaryo)

---

## 1. Ne yapacak

Şartname (kullanıcının kendi sözleriyle, 9 madde) dört kümede toplanıyor:

| Küme | Maddeler | Özet |
|---|---|---|
| Kişilik | 1, 7, 8, 9 | İnsan gibi konuşur, mantık kurar, sorularla boğmaz, betimleyerek heyecanlandırır |
| Akıl yürütme | 2, 3 | Tarif → "bu nasıl bir tatil" teşhisi (**siteden bağımsız**) → katalogda ara → yoksa dürüst köprü |
| Dünya bilgisi | 5, 6 | Sezon/takvim ve şehir karakteri hakimiyeti; öneriyle çelişmez |
| Ticari tavır | 4 | Turu aktif pazarlar — dürüstlük sınırı içinde |

**Kararlar:**
1. **Betimleme sınırı:** tur verisinde yazan + şehir/destinasyon genel bilgisi. Tura özel uydurma detay (odada jakuzi, deniz manzarası) **yasak**.
2. **Hafıza:** yalnız o oturum. Kalıcı kullanıcı profili yok.
3. **Kapsam:** tur eşleştirme + tur detay soruları. Lead toplama / WhatsApp devri / şikayet akışı **kapsam dışı** (v1'de duruyor).

---

## 2. Mimari

Model **düşünür ve yazar**; katalog gerçeklerini **araçlardan** alır. v1'in elle yazılmış 10 fazlı regex zinciri tamamen kalkar.

```
Kullanıcı mesajı
   ↓
[1] Yansıtma cümlesi (araçsız, anında stream edilir)  ← gecikmeyi maskeler
   ↓
[2] Model → araç çağrısı/çağrıları (paralel, maks 3 tur)
   ↓
[3] PHP araçları deterministik veri döndürür
   ↓
[4] Model nihai cevabı yazar
   ↓
[5] Doğrulama (sayı + tur adı beyaz listesi) → stream + kartlar
```

**Model:** `gpt-5.4` (mini değil). "İstediğim zekada değil" şikayetinin doğrudan cevabı — v1'de konuşan yüzey `gpt-4o-mini` idi.
**Maliyet:** tur başına ≈ 3-4K girdi + ~400 çıktı ≈ **1,5 kuruş**. Sistem promptu ve araç şemaları sabit olduğundan önbellekli girdi indirimi (%90) devrede. Ayda 1000 mesaj ≈ **$16**.

**Araç döngüsü senkron koşar, yalnız nihai cevap stream edilir.** Gerekçe: `openai-php`'de `StreamResponse::fake()` yok; senkron döngü mevcut hermetik test düzenini (`OpenAI::fake` sıralı kuyruk) hiç değiştirmeden çalıştırır ve SSE arayüzü korunur.

---

## 3. Araçlar (4 tane)

### `tur_ara`
Rubrik eşleştiricisini çağırır. **Modelin doldurduğu 10 boyutlu vektör + sert filtreler** girer, sıralı turlar çıkar.

```jsonc
// girdi
{
  "boyutlar": {                        // her biri opsiyonel; emin olunmayan YAZILMAZ
    "tempo":   {"deger": 20, "kanit": "kafamı dinlemek istiyorum"},
    "sosyallik": {"deger": 10, "kanit": "kimse beni rahatsız etmesin"}
  },
  "onemli": ["tempo"],                 // ağırlık çarpanı (≤2)
  "filtre": {"aylar": [9], "gun_min": 3, "gun_max": 5,
             "butce_max_try": 40000, "kalkis_sehri": "İstanbul",
             "destinasyon": "Kapadokya", "yurt_disi": false}
}
// çıktı
{ "turlar": [ /* kart: id, baslik, fiyat, sure, gorsel, url, acenta, kalkis */ ],
  "kapsam": 0.6,                       // ölçülebilen ağırlık oranı
  "taban_alti": false,                  // hiçbiri %60'ı geçmedi
  "karsilanmayan": ["sosyallik"],      // hangi istek karşılanamadı → köprü cümlesinin kaynağı
  "gevsetme_notlari": ["Bütçe %20 gevşetildi"],
  "sor": "butce" }                      // veriden türeyen tek soru (bkz. §7)
```

**Kanıt disiplini (kritik).** Model her boyut için kullanıcının **kendi cümlesinden alıntı** vermek zorunda. PHP tarafında alıntı transkriptte geçiyor mu diye kontrol edilir; geçmiyorsa **o boyut düşürülür** (ağırlık 0). Tur tarafında zaten uyguladığımız kuralın (`ScoreTourRubricJob`: kanıt yoksa `null`) kullanıcı tarafındaki karşılığı. Bu olmadan model 10 boyutun 10'unu da doldurur ve "sessiz sakin tatil" cümlesinden `gastronomi: 50` üretir.

**Ölçek çıpası.** Araç şemasının açıklamasına quiz şıklarının kalibre değerleri örnek olarak konur ("sakin kasaba ≈ kalabaliklik 20") — testten gelen ve sohbetten gelen vektörler aynı ölçekte kalsın. **Kabul testi:** quiz cevaplarının metin karşılığı `tur_ara`'ya verildiğinde aynı ilk 3 tur çıkmalı.

### `tur_detay`
Seçilen turun program (gün gün), otel, dahil/hariç, iptal koşulu, rehber bilgisi, kampanya, tempo ve şehir profili. Gövdesi hazır: `AiSearchController::tourFactSheet()` (private, `:2104-2166`) servise çıkarılacak.

### `sehir_bilgisi`
`DestinationProfileService::get()` üstünde yapılandırılmış çıktı: kalabalıklık, canlılık, en iyi/kalabalık aylar, iklim, vibe etiketleri, vize, özet.
⚠️ **`veri_var: false` bayrağı zorunlu** — zenginleşmemiş şehirde servis sessizce 0.50/0.50 döndürüyor; bayrak olmazsa model "orta yoğunlukta bir şehir" diye uydurur.

### `envanter_ozeti`
Hangi destinasyonlarda kaç tur var + **sattığımız ürün tipleri**. İkincisi yeni: villa/özel mülk gibi hiç satmadığımız ürün tipini model **tahminle değil veriden** reddedebilsin (şartnamenin villa örneği bunu gerektiriyor).

**Beşinci araca (sezon) gerek yok:** bugünün tarihi + sezon + resmî tatil aralıkları (`config/special_periods.php`) sistem promptuna enjekte edilir; `best_months`/`crowded_months` zaten `sehir_bilgisi`'nde.

---

## 4. Uydurmayı engelleme — üç katman

1. **Gerçekleri kod basar.** Fiyat, süre, tarih, kalkış, acenta → `TourMatcher::card()` çıktısından tur kartına. Bu zaten hazır.
2. **Beyaz liste doğrulaması.** Cevap buffer'da tutulur; metindeki **tur adları** son araç sonucundaki id/başlık kümesinde mi, **sayılar** araç çıktısında geçiyor mu diye deterministik kontrol edilir.
3. **İhlalde cümle düşürülür, cevap yeniden üretilmez.** Tam yeniden üretim hem pahalı hem kullanıcı yazının silinip yeniden yazılmasını görür.

⚠️ **Önemli ayrım:** tatil **tipi** ("tarif ettiğin şey villa tatili") kontrol dışıdır — şartname madde 3 bunu açıkça istiyor. Kontrol yalnız **tur varlığı** ve **sayılar** üzerinedir. Naif bir "araç sonucunda yoksa reddet" kuralı villa örneğini yanlış-pozitif olarak öldürürdü.

**Modeli tamamen susturmuyoruz.** "Gerçekleri hiç söyleme" kuralı pazarlama dilini jenerikleştirir ve v1'in şikayet edilen sesini üretir ("bu tur tam senin tarif ettiğin his"). Model somut çıpayı kullanır ("üç gece kalıyorsun, ikinci gün sabahı boş"), doğrulama katmanı yanlışı yakalar.

---

## 5. Hafıza — yapısal durum, ham transkript değil

Ham transkript + araç çıktıları biriktirilirse maliyet tur sayısının karesiyle büyür (`tur_detay` tek başına 2-4K token). Bunun yerine kodda tutulan durum nesnesi her turda tek kısa mesaj olarak enjekte edilir:

```jsonc
{ "rubrik_vektor": {...}, "agirliklar": {...},
  "kisitlar": {"butce": 40000, "aylar": [9], "kalkis": "İstanbul", "cocuk_yasi": 4},
  "gosterilen_tur_idleri": [12, 45], "reddedilenler": [45],
  "konusulan_sehirler": ["Kapadokya"] }
```

Ham araç çıktıları yalnız **son 2 tur** için saklanır; öncekiler `{id, başlık}` stub'ına iner. Model eski bir turu tekrar konuşmak isterse `tur_detay`'ı yeniden çağırır (araç ucuz, bağlam pahalı).

Bu, "tüm konuşmayı hatırla" maddesini **daha iyi** karşılar: model 20 mesaj önce söyleneni ezberden değil, yapılandırılmış durumdan hatırlar. Oturum kapanınca durum silinir (karar 2).

---

## 6. Gecikme ve streaming

Araç döngüsü + reasoning = ilk token'a kadar 6-12 saniye sessizlik riski. "Gerçek insan gibi" olmak 10 saniye susan bir muhataba yenilir.

- **İlk tur araçsız:** model tek cümlelik yansıtma üretir ("sessizlik ve mahremiyet arıyorsun, anladım — bakıyorum") ve bu **anında** stream edilir; araç çağrıları o cümle akarken başlar. Şartname maddeleri 1 ve 9'a doğrudan hizmet eder.
- Bağımsız araçlar (`sehir_bilgisi` + `envanter_ozeti`) **tek turda paralel** çağrılır.
- **Araç turu üst sınırı 3**; aşılırsa elde olanla cevap verilir.
- SSE'ye faz olayları basılır (`arıyorum`, `3 tur buldum`) — v1'de bu altyapı var, korunuyor.

---

## 7. Soru sorma disiplini

Madde 8 ("sorularla boğma") ile eşleştirmenin ihtiyacı olan bütçe/tarih bilgisi çatışıyor. Bütçe hiç sorulmazsa 90 bin TL'lik turu 15 bin bütçeli kullanıcıya coşkuyla pazarlarız — bu bir soru sormaktan çok daha kötü.

**Çözüm: soru kararı modelden alınır, veriden türetilir.** `tur_ara` çıktısındaki `sor` alanı: ilk 3 turun fiyat yayılımı 2 kattan fazlaysa `butce`, aday sayısı çok fazlaysa `ay`, aksi halde `yok`. Sistem promptunda tek kural: *"araç `sor` döndürürse **tam bir** soru sor, döndürmezse sorma."*

Böylece asla iki soru üst üste gelmez ve soru havadan değil sonuç dağılımından doğar — "üç tur buldum ama fiyatları çok ayrışıyor, bir aralık verir misin?" cümlesi insan hissini bozmaz, güçlendirir.

---

## 8. Skor şişmesi koruması

`skor()` formülü aktif ağırlık toplamına bölüyor. Serbest sohbette 2 boyut aktifse o iki boyutta ortalama bir tur bile **%90+** alır; "%92 uyumlu" rozeti kullanıcıya yalan söyler ve ilk yanlış öneride güven yanar.

- Ölçülen ağırlık oranı (`kapsam`) eşiğin altındaysa **yüzde gösterilmez**, kart "kısmi eşleşme" etiketiyle çıkar.
- Tur tarafında `null` boyutlar cezasız kaldığı için **açıklaması zayıf tur listenin başına çıkma** eğiliminde; turun ölçülebilen boyut sayısı sıralamada eşitlik bozucu olarak kullanılır.

---

## 9. Sistem promptu iskeleti (~40 satır)

v1'in yorum promptu 9 kural + persona + örnekler + injection koruması + RAG bağlamı + tur fişini aynı anda taşıyordu; "metni takip edemiyor" şikayetinin kaynağı bu. Yenisi:

```
KİMLİK: turXtur'un tur danışmanısın. Türkçe, samimi, "sen" diliyle konuşursun.
BUGÜN: {tarih} · SEZON: {sezon} · YAKLAŞAN ÖZEL GÜNLER: {liste}

AKIL YÜRÜTME (her istekte sırayla):
1. Kullanıcının tarif ettiği tatilin NE TÜR bir tatil olduğunu kendi bilginle belirle.
2. Bu teşhisi kullanıcıya söyle — katalogda karşılığı olmasa bile.
3. tur_ara ile katalogda ara.
4. Yoksa dürüstçe söyle ve en yakın turu GEREKÇESİYLE öner.

SERT KURALLAR:
- Araç sonucunda olmayan tur adı, fiyat veya tarih yazma.
- Turun programında yazmayan tura özel detay uydurma (oda özellikleri, manzara, ikram).
- Sezona aykırı öneri yapma.
- Araç "sor" döndürmedikçe soru sorma; döndürürse tek soru sor.

ÜSLUP: tatili gözünde canlandır, sat. Kuru liste değil sahne kur.
```

Bağlam prompta doldurulmaz — araçlar getirir. Few-shot: villa örneği (şartname madde 3) + bir sezon tuzağı.

---

## 10. Kod yapısı

```
app/Services/Chat/
    ChatAgent.php            # döngü: model → araç → model (~150 satır)
    ConversationState.php    # yapısal hafıza (§5)
    ResponseValidator.php    # beyaz liste doğrulaması (§4)
    LlmProfileBuilder.php    # model çıktısı → TourMatcher profili (~40 satır)
    Tools/TurAra.php  TurDetay.php  SehirBilgisi.php  EnvanterOzeti.php
app/Http/Controllers/ChatV2Controller.php   # ince: doğrula, akıt, kaydet
```

**Sert kural: hiçbir dosya 300 satırı geçmeyecek.** 2900 satırlık controller'a bir daha dönmemenin tek yolu bu. Her araç ayrı ayrı, LLM'siz birim testiyle doğrulanır.

---

## 11. Mevcut kodda gereken değişiklikler

| Ne | Neden |
|---|---|
| `OpenAiChatParams::tools()` eklenecek | Mevcut `json()` `response_format`'ı sabitliyor; `tools` ile birlikte gönderilince araç akışını bozar. `reasoning_effort` de parametrik olmalı |
| `tourFactSheet()` servise çıkarılacak | `tur_detay`'ın gövdesi; şu an controller'da private |
| `TourMatcher`: destinasyon + yurt dışı filtresi, parametrik `top_n`, skor cache | Sohbet "Kapadokya'da ne var?" diyebilmeli; tur başına 1-3 araç çağrısı olacak |
| `TourMatcher` çıktısına `karsilanmayan` + `kapsam` + `sor` | Köprü cümlesi, skor şişmesi ve soru disiplini için makine-okunur sinyal |
| `sehir_bilgisi`'ne `veri_var` bayrağı | Zenginleşmemiş şehirde sessiz yalan riski |
| Ayrı özellik bayrağı (`AI_CHAT_V2_ENABLED`) | Mevcut bayrak v1'i kapatıyor; v2 ayrı açılabilmeli |
| `filtre` anahtarı ölü | `QuizEvaluator` döndürüyor, `TourMatcher` okumuyor — ya uygulanmalı ya sözleşmeden çıkmalı |

**Silinecekler** (kapsam kararlarıyla gereksiz): lead/devir/şikayet bloğu, netleştirme makinesi, `routeMessage`+`router_model`, `detectNatureIntent`/`scoreVibeMatch` ailesi (~500 satır — rubrik boyutları yerlerini alıyor).
**Silinmeyecekler:** `PiiMasker`, `wrapUserInputSafely`, SSE iskeleti, `DestinationProfileService`, rubrik ailesi, `QuizEvaluator` (tur eşleştirme testi kullanıyor).

---

## 12. Kabul kriterleri

**Yazılacak ilk şey kod değil, eval seti.** v1'in "sazan sarmalı"nın sebebi: iyi/kötüyü ölçen bir şey yoktu, her değişiklik hisle değerlendirildi.

24 senaryo hazır ([`resources/eval/chatbot-v2-senaryolar.json`](resources/eval/chatbot-v2-senaryolar.json)): belirsiz açılış, balayı uydurma tuzağı, çok turlu hafıza takibi, sezon tuzakları, villa/safari/kruvaziyer yokluk senaryoları, prompt injection, sinirli kullanıcı, göreli tarihler (bayram/sömestr), çelişkili istekler.

Her senaryoda **somut başarısızlık işareti** var ("temmuz için kayak önerirse", "katalogda olmayan tur adı üretirse", "kullanıcının söylediği bütçeyi tekrar sorarsa"). Assert'ler yalnız metinde değil **araç çağrı dizisinde** de yapılır: hangi araç çağrıldı, `aylar` set edildi mi, `kanit` alanları doldu mu.

---

## 13. Fazlar

| Faz | İş | Bağımlılık |
|---|---|---|
| 0 | Rubrik backfill + gün kırpma sınırı 300→1000 | **Bu yapılmadan `tur_ara` boş döner** |
| 1 | Eval koşucusu + 24 senaryo | — |
| 2 | `OpenAiChatParams::tools()`, araç sınıfları, birim testleri | — |
| 3 | `ChatAgent` + durum + doğrulayıcı | Faz 2 |
| 4 | Controller + SSE + ön yüz | Faz 3 |
| 5 | Eval koşumu → prompt/model ayarı → yayın | Faz 1-4 |

---

## 14. Bekleyen kararlar

1. **Vize kapsamı.** Detay alanları (`visa_general/documents/fees/notes`) `2026_07_05_200000` migration'ında silindi; geriye boolean kaldı. "Tur detay soruları" kapsamı vizeyi içeriyor → ya alanlar geri gelmeli ya vize detayı kapsam dışı sayılmalı. Ara yol yok: şartnamede olup veride olmayan her alan uydurma baskısıdır.
2. **`AiSearchLog` ne olacak?** 7 tüketicisi var (CTR öğrenimi, ağırlık kalibrasyonu, kalite raporu, acenta paneli, tur sayfasındaki AI bağlam barı). `tur_ara` log yazsın mı, yoksa bu tüketiciler emekliye mi ayrılsın?
3. **Ürün tipi alanı.** Villa/özel mülk gibi hiç satmadığımız tipleri veriden reddedebilmek için katalogda küçük bir `urun_tipi` alanı gerekiyor mu, yoksa `envanter_ozeti` sabit bir "sattıklarımız" listesiyle mi yetinsin?
4. **Rubrik `needs_review` akışı.** Editör onayı yapılmazsa katalog kalıcı olarak küçük kalır (o turlar canlı eşleştirmeye girmiyor).
