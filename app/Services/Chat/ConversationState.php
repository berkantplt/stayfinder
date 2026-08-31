<?php

namespace App\Services\Chat;

use App\Services\Chat\Tools\SehirBilgisi;
use App\Services\Chat\Tools\TurAra;
use App\Services\Chat\Tools\TurDetay;

/**
 * Oturumluk yapısal hafıza (karar 2: kalıcı profil YOK).
 *
 * Neden ham transkript değil: araç çıktıları (tur_detay tek başına 2-4K token)
 * her turda yeniden faturalanır → maliyet tur sayısının KARESİYLE büyür.
 * Bunun yerine konuşmadan damıtılan durum tek kısa mesaj olarak enjekte edilir.
 * Yan fayda: model 20 mesaj önce söyleneni ezberden değil, yapıdan hatırlar —
 * şartname madde 1'ini ("baştan sona hatırlamalı") daha güvenilir karşılar.
 *
 * GÜVENLİK: bu durum bir SYSTEM mesajına yazıldığı için modelin ürettiği hiçbir
 * serbest metin doğrudan içeri alınmaz — yalnız beyaz listedeki filtre anahtarları
 * ve sayısal/kısa değerler saklanır. Aksi halde model uydurduğu bir anahtara
 * talimat sığdırıp bir sonraki tura sistem yetkisiyle taşıyabilirdi.
 */
class ConversationState
{
    /** tur_ara filtre şemasındaki anahtarlar — dışındakiler yok sayılır. */
    private const FILTRE_ANAHTARLARI = [
        'aylar', 'gun_min', 'gun_max', 'butce_max_try', 'kalkis_sehri', 'destinasyon', 'yurt_disi',
    ];

    private const MAX_TUR_HAFIZASI = 12;

    public function __construct(
        /** @var array<string, float> KABUL EDİLEN boyut değerleri (kanıtı doğrulananlar) */
        public array $degerler = [],
        /** @var array<string, float> aynı aramanın ağırlıkları ("diğerleri" için) */
        public array $agirliklar = [],
        /** @var array<string, mixed> sert filtreler (beyaz listeli) */
        public array $kisitlar = [],
        /** @var array<int, array{id: int, baslik: string}> gösterilen turlar */
        public array $gosterilenTurlar = [],
        /** @var string[] hakkında GERÇEK veri bulunan şehirler */
        public array $sehirler = [],
    ) {}

    public static function fromArray(?array $data): self
    {
        $durum = new self();
        foreach ((array) ($data['degerler'] ?? []) as $d => $v) {
            if (is_string($d) && is_numeric($v)) {
                $durum->degerler[$d] = (float) $v;
            }
        }
        foreach ((array) ($data['agirliklar'] ?? []) as $d => $v) {
            if (is_string($d) && is_numeric($v)) {
                $durum->agirliklar[$d] = (float) $v;
            }
        }
        $durum->kisitlar = $durum->temizFiltre((array) ($data['kisitlar'] ?? []));
        foreach ((array) ($data['gosterilen_turlar'] ?? []) as $t) {
            if (isset($t['id'], $t['baslik'])) {
                $durum->gosterilenTurlar[(int) $t['id']] = ['id' => (int) $t['id'], 'baslik' => (string) $t['baslik']];
            }
        }
        foreach ((array) ($data['sehirler'] ?? []) as $s) {
            if (is_string($s)) {
                $durum->sehirler[] = mb_substr($s, 0, 60, 'UTF-8');
            }
        }

        return $durum;
    }

    public function toArray(): array
    {
        return [
            'degerler' => $this->degerler,
            'agirliklar' => $this->agirliklar,
            'kisitlar' => $this->kisitlar,
            'gosterilen_turlar' => array_values($this->gosterilenTurlar),
            'sehirler' => $this->sehirler,
        ];
    }

    /**
     * Araç sonucundan durumu günceller.
     *
     * Boyut değerleri modelin İDDİASINDAN değil, aracın KABUL ETTİĞİ profilden
     * alınır: kanıtı doğrulanamayıp düşen boyut hafızaya girerse kanıt disiplini
     * bu kanaldan atlanır ve reddedilen çıkarım kalıcı bağlam olurdu.
     */
    public function absorb(string $tool, array $args, array $result): void
    {
        if ($tool === TurAra::name()) {
            $this->kisitlar = array_merge($this->kisitlar, $this->temizFiltre((array) ($args['filtre'] ?? [])));

            foreach ((array) ($result['kabul_edilen_degerler'] ?? []) as $d => $v) {
                if (is_string($d) && is_numeric($v)) {
                    $this->degerler[$d] = (float) $v;
                }
            }
            foreach ((array) ($result['kabul_edilen_agirliklar'] ?? []) as $d => $v) {
                if (is_string($d) && is_numeric($v)) {
                    $this->agirliklar[$d] = (float) $v;
                }
            }
            // Yakın turlar da hatırlanır: kullanıcıya KART olarak gösterildiler,
            // "bu konuşmada gösterdiklerin" listesinden düşerlerse model onları
            // yeniden sunar ve "diğerleri" görünümü de tekrar eder.
            foreach (array_merge((array) ($result['turlar'] ?? []), (array) ($result['yakin_turlar'] ?? [])) as $t) {
                $this->turEkle($t['id'] ?? null, $t['title'] ?? null);
            }
        }

        if ($tool === TurDetay::name()) {
            $this->turEkle($result['id'] ?? null, $result['baslik'] ?? null);
        }

        // Yalnız hakkında GERÇEK veri olan şehir hatırlanır; veri_var=false olan
        // şehri "konuşuldu" diye geri vermek modeli niteleme yapmaya iter
        if ($tool === SehirBilgisi::name() && ($result['veri_var'] ?? false) === true && ! empty($args['sehir'])) {
            $sehir = mb_substr((string) $args['sehir'], 0, 60, 'UTF-8');
            if (! in_array($sehir, $this->sehirler, true)) {
                $this->sehirler[] = $sehir;
            }
        }
    }

    private function turEkle(mixed $id, mixed $baslik): void
    {
        if (! is_numeric($id) || ! is_string($baslik) || $baslik === '') {
            return;
        }
        // Tekrar gösterilen tur listenin SONUNA taşınsın: son-N penceresi
        // "en son konuşulan"ı kapsasın
        unset($this->gosterilenTurlar[(int) $id]);
        $this->gosterilenTurlar[(int) $id] = ['id' => (int) $id, 'baslik' => mb_substr($baslik, 0, 120, 'UTF-8')];

        if (count($this->gosterilenTurlar) > self::MAX_TUR_HAFIZASI) {
            array_shift($this->gosterilenTurlar);
        }
    }

    /** @return array<string, mixed> yalnız bilinen anahtarlar, sınırlı tipler */
    private function temizFiltre(array $filtre): array
    {
        $temiz = [];
        foreach (self::FILTRE_ANAHTARLARI as $k) {
            if (! array_key_exists($k, $filtre)) {
                continue;
            }
            $v = $filtre[$k];
            $temiz[$k] = match ($k) {
                'aylar' => array_values(array_filter(array_map('intval', (array) $v), fn ($a) => $a >= 1 && $a <= 12)),
                'gun_min', 'gun_max' => max(1, min(60, (int) $v)),
                'butce_max_try' => max(0.0, (float) $v),
                'yurt_disi' => (bool) $v,
                default => mb_substr(trim((string) $v), 0, 60, 'UTF-8'),
            };
            if ($temiz[$k] === [] || $temiz[$k] === '') {
                unset($temiz[$k]);
            }
        }

        return $temiz;
    }

    /**
     * Bir sonraki tur_ara çağrısına otomatik uygulanacak kısıtlar. Model kısıtı
     * tekrar geçirmeyi unutursa arama sert filtresiz koşup kullanıcıya aynı soru
     * yeniden sordurulurdu (şartname madde 1 ve 8 birlikte ihlal).
     */
    public function varsayilanFiltre(): array
    {
        return $this->kisitlar;
    }

    /** Doğrulayıcıya verilen: durumda geçen sayılar (araçsız turda cümle düşmesin). */
    public function bilinenSayilar(): array
    {
        $sayilar = [];
        foreach ($this->kisitlar as $k => $v) {
            if (is_numeric($v)) {
                $sayilar[] = (float) $v;
            }
        }

        return $sayilar;
    }

    /** Modele enjekte edilen kısa durum özeti. Boşsa hiç mesaj eklenmez. */
    public function promptOzeti(): ?string
    {
        $satirlar = [];

        if ($this->kisitlar !== []) {
            $parcalar = [];
            foreach ($this->kisitlar as $k => $v) {
                $parcalar[] = $k.'='.(is_array($v) ? implode(',', $v) : (is_bool($v) ? ($v ? 'evet' : 'hayır') : $v));
            }
            $satirlar[] = 'Kullanıcının verdiği kısıtlar: '.implode(' · ', $parcalar);
        }
        if ($this->degerler !== []) {
            $parcalar = [];
            foreach ($this->degerler as $d => $v) {
                $parcalar[] = $d.'='.(int) $v;
            }
            $satirlar[] = 'Doğrulanmış tercih profili (0-100): '.implode(' · ', $parcalar);
        }
        if ($this->gosterilenTurlar !== []) {
            $parcalar = array_map(fn ($t) => '#'.$t['id'].' '.$t['baslik'], array_slice($this->gosterilenTurlar, -6));
            $satirlar[] = 'Bu konuşmada gösterdiğin turlar: '.implode(' | ', $parcalar);
        }
        if ($this->sehirler !== []) {
            $satirlar[] = 'Veri bulunan şehirler: '.implode(', ', $this->sehirler);
        }

        return $satirlar === [] ? null : "KONUŞMA DURUMU (kullanıcıya tekrar sorma):\n".implode("\n", $satirlar);
    }

    public function gosterilenIdler(): array
    {
        return array_keys($this->gosterilenTurlar);
    }
}
