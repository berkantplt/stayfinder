<?php

namespace App\Http\Controllers;

use App\Services\AiSearch\PiiMasker;
use App\Services\Chat\ChatAgent;
use App\Services\Chat\ConversationState;
use App\Services\Chat\Tools\TurAra;
use App\Support\SseStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chatbot v2 uçları. İnce controller: doğrula → akıt → oturuma yaz.
 *
 * HAFIZA (karar 2): konuşma ve durum YALNIZ oturumda tutulur, veritabanına
 * yazılmaz. Sekme kapanınca unutulur — kalıcı profil yok, KVKK yükü düşük.
 */
class ChatV2Controller extends Controller
{
    private const OTURUM_ANAHTARI = 'chat_v2';

    /** Bağlam penceresi: son N tur. Yapısal durum zaten ayrı taşınıyor. */
    private const GECMIS_LIMITI = 12;

    /** "Diğerleri" görünümünde toplam kaç tur gösterilir (chat'teki 5 dahil). */
    private const GENISLETILMIS_LIMIT = 20;

    /** Basılan kart kimliği hafızası — oturum sınırsız büyümesin. */
    private const BASILAN_KART_HAFIZASI = 60;

    public function __construct(private readonly ChatAgent $agent) {}

    /**
     * Basılan kart kimliklerini biriktirir (son N tanesi).
     *
     * @param  array<int, int>  $basilan
     * @param  array<int, array<string, mixed>>  $turlar
     * @return array<int, int>
     */
    private function basilanlaraEkle(array $basilan, array $turlar): array
    {
        foreach ($turlar as $tur) {
            $id = (int) ($tur['id'] ?? 0);
            if ($id > 0 && ! in_array($id, $basilan, true)) {
                $basilan[] = $id;
            }
        }

        return array_slice($basilan, -self::BASILAN_KART_HAFIZASI);
    }

    /**
     * Kart şeridi basılsın mı? Yalnız EN AZ BİR yeni tur varsa.
     *
     * Canlı şikayet: kullanıcı "Kapadokya bu mevsimde nasıl?" diye bilgi sordu,
     * model buna da tur_ara çağırdı ve oturumdaki profil değişmediği için aynı
     * turlar döndü — cevabın altına az önce gösterilen kartlar ikinci kez basıldı.
     * Prompt kuralı bunu azaltır ama garanti etmez; tekrarı imkânsız kılan yer burası.
     *
     * @param  array<int, array<string, mixed>>  $turlar
     * @param  array<int, int>  $oncekiIdler
     */
    private function yeniKartVar(array $turlar, array $oncekiIdler): bool
    {
        foreach ($turlar as $tur) {
            if (! in_array((int) ($tur['id'] ?? 0), $oncekiIdler, true)) {
                return true;
            }
        }

        return false;
    }

    /** SSE akışı. Ortam kurulumu + emit üretimi SseStream'de (nginx altında kazanılmış ayarlar). */
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // PII maskeleme v1'den korunuyor: kart/TC numarası ne oturuma ne LLM'e gider
        $mesaj = PiiMasker::mask(trim($validated['message']))['text'];

        $oturum = (array) $request->session()->get(self::OTURUM_ANAHTARI, []);
        $gecmis = array_slice((array) ($oturum['gecmis'] ?? []), -self::GECMIS_LIMITI);
        $durum = ConversationState::fromArray($oturum['durum'] ?? null);
        // Ölçü BASILAN kartlar, durumun gösterilen tur listesi DEĞİL: o liste
        // araç sonucundan doluyor, yani modele dönen turu "kullanıcı gördü"
        // sayıyor. Kart basılamadığı bir turdan sonra kapı sonsuza dek kapanıp
        // sohbet bir daha hiç kart göstermiyordu.
        $basilanIdler = array_values(array_filter(array_map(
            'intval',
            (array) ($oturum['basilan_kartlar'] ?? []),
        )));

        return response()->stream(function () use ($request, $mesaj, $gecmis, $durum, $basilanIdler) {
            $emit = SseStream::baslat();

            try {
                $sonuc = $this->agent->handle(
                    $mesaj,
                    $gecmis,
                    $durum,
                    fn (string $parca) => $emit('delta', ['text' => $parca]),
                    // Araçlar koşarken ne yaptığını göster — sessizlik hem kötü
                    // deneyim hem istemci bekçisini tetikliyordu
                    fn (string $metin) => $emit('faz', ['text' => $metin]),
                );

                if ($sonuc['turlar'] !== [] && $this->yeniKartVar($sonuc['turlar'], $basilanIdler)) {
                    // toplam: "diğerleri" butonunun kaç tur daha olduğunu bilmesi için
                    $toplam = 0;
                    foreach ($sonuc['iz'] as $adim) {
                        $toplam = max($toplam, (int) ($adim['toplam_eslesme'] ?? 0));
                    }
                    $emit('tours', [
                        'items' => $sonuc['turlar'],
                        'toplam' => $toplam,
                        'kalan' => max(0, min($toplam, self::GENISLETILMIS_LIMIT) - count($sonuc['turlar'])),
                    ]);

                    $basilanIdler = $this->basilanlaraEkle($basilanIdler, $sonuc['turlar']);
                }

                // Oturuma yaz: kullanıcının GÖRDÜĞÜ metin geçmişe girer, yoksa
                // model bir sonraki turda kendi söylediğini bilmez
                $gecmis[] = ['role' => 'user', 'content' => $mesaj];
                $gecmis[] = ['role' => 'assistant', 'content' => $sonuc['metin']];

                $request->session()->put(self::OTURUM_ANAHTARI, [
                    'gecmis' => array_slice($gecmis, -self::GECMIS_LIMITI),
                    'durum' => $sonuc['durum']->toArray(),
                    'basilan_kartlar' => $basilanIdler,
                ]);
                $request->session()->save();

                $emit('done', ['is_error' => (bool) $sonuc['hata']]);
            } catch (\Throwable $e) {
                // Ham exception mesajı kullanıcıya SIZMASIN
                Log::error('[ChatV2] stream hata: '.$e->getMessage());
                $emit('error', ['message' => 'Şu an bir sorun oluştu, mesajını tekrar gönderir misin?']);
                $emit('done', ['is_error' => true]);
            }
        }, 200, SseStream::headers());
    }

    /**
     * "Diğerleri": chat'te gösterilen 5 turdan sonrakiler.
     * Kurgu TurAra::genisletilmisListe'de — controller yalnız oturumu okur.
     */
    public function more(Request $request, TurAra $turAra)
    {
        $oturum = (array) $request->session()->get(self::OTURUM_ANAHTARI, []);
        $durum = ConversationState::fromArray($oturum['durum'] ?? null);

        if ($durum->agirliklar === [] || array_sum($durum->agirliklar) <= 0) {
            return response()->json(['items' => [], 'not' => 'Önce bir tatil tarifi gerekiyor.']);
        }

        $sonuc = $turAra->genisletilmisListe($durum, self::GENISLETILMIS_LIMIT);

        // Bu kartlar da ekrana basılıyor; kaydedilmezse sonraki turda "yeni"
        // sayılıp ikinci kez basılırlardı.
        $oturum['basilan_kartlar'] = $this->basilanlaraEkle(
            array_map('intval', (array) ($oturum['basilan_kartlar'] ?? [])),
            $sonuc['items'] ?? [],
        );
        $request->session()->put(self::OTURUM_ANAHTARI, $oturum);

        return response()->json($sonuc);
    }

    /** Konuşmayı sıfırla (oturumluk hafızayı temizler). */
    public function reset(Request $request)
    {
        $request->session()->forget(self::OTURUM_ANAHTARI);

        return response()->json(['ok' => true]);
    }
}
