@extends('layouts.app')
@section('title', 'Keşif Rehberi: '.$guide->destination_input.' — turXtur')
@section('description', $guide->duration_days.' günlük '.$guide->destination_input.' keşif rehberi: gün gün program, gezilecek yerler, yerel lezzetler ve seyahat tavsiyeleri.')
{{-- Kişiye özel uuid sayfası — arama motoruna girmesin --}}
@section('robots', 'noindex, nofollow')

@section('content')
<style>
    .dg-wrap { max-width: 860px; margin: 0 auto; }
    .dg-panel { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow); padding: 24px; margin-bottom: 20px; }
    .dg-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .dg-chip { border: 1px solid var(--border); background: var(--bg); color: var(--text-sec); border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700; }
    .dg-chip-btn { border: 1px solid var(--border); background: var(--white); color: var(--text-sec); border-radius: 999px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .15s; }
    .dg-chip-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent-dark); }
    .dg-chip-btn.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .dg-chip-btn:disabled { opacity: .5; cursor: default; }
    .dg-h2 { font-size: 20px; font-weight: 800; color: var(--text); margin: 28px 0 14px; display: flex; align-items: center; gap: 8px; }
    .dg-day { border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--white); box-shadow: var(--shadow); margin-bottom: 16px; overflow: hidden; }
    .dg-day-head { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #fff; padding: 14px 20px; }
    .dg-day-head h3 { font-size: 16px; font-weight: 800; }
    .dg-day-head p { font-size: 13px; opacity: .85; margin-top: 2px; }
    .dg-day-body { padding: 18px 20px; position: relative; }
    /* Kapalı hâl: ilk birkaç satır görünür, altı karta doğru şeffaflaşır.
       JS kapalıysa aşağıdaki <noscript> bu sınırı kaldırır — içerik saklı kalmaz. */
    .dg-day-body.dg-kapali { max-height: 150px; overflow: hidden; }
    .dg-day-body.dg-kapali::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 88px; background: linear-gradient(to bottom, rgba(255,255,255,0), var(--white) 82%); pointer-events: none; }
    .dg-day-ac { width: 100%; border: 0; border-top: 1px solid var(--border); background: var(--white); color: var(--accent-dark); font-family: inherit; font-size: 13px; font-weight: 700; padding: 11px 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background .15s; }
    .dg-day-ac:hover { background: var(--bg); }
    .dg-day-ac .dg-ok { transition: transform .2s; }
    .dg-day-ac[aria-expanded="true"] .dg-ok { transform: rotate(180deg); }
    .dg-period { margin-bottom: 14px; }
    .dg-period-title { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--accent-dark); margin-bottom: 8px; }
    .dg-place { padding: 10px 12px; background: var(--bg); border-radius: var(--radius); margin-bottom: 8px; }
    .dg-place-name { font-weight: 700; font-size: 14px; color: var(--text); display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .dg-place-name .dg-mini { font-size: 11px; font-weight: 600; color: var(--text-muted); background: var(--white); border: 1px solid var(--border); border-radius: 6px; padding: 1px 6px; }
    .dg-place p { font-size: 13px; color: var(--text-sec); margin-top: 4px; line-height: 1.5; }
    .dg-tip { background: var(--accent-bg); border: 1px dashed var(--accent); border-radius: var(--radius); padding: 10px 14px; font-size: 13px; color: var(--accent-dark); }
    .dg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 640px) { .dg-grid { grid-template-columns: 1fr; } }
    .dg-item { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; }
    .dg-item b { font-size: 14px; color: var(--text); }
    .dg-item p { font-size: 13px; color: var(--text-sec); margin-top: 4px; line-height: 1.5; }
    .dg-item .dg-why { font-size: 12px; color: var(--accent-dark); margin-top: 6px; }
    .dg-tips li { font-size: 14px; color: var(--text-sec); line-height: 1.6; margin-bottom: 8px; padding-left: 4px; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
</style>
<noscript><style>.dg-day-body.dg-kapali { max-height: none; } .dg-day-body.dg-kapali::after { display: none; } .dg-day-ac { display: none; }</style></noscript>

<div class="container" style="padding-top:40px;padding-bottom:60px;">
    @include('partials.breadcrumb', ['items' => [
        ['name' => 'Keşif Rehberi', 'url' => route('discovery.index')],
        ['name' => $guide->destination_input],
    ]])

    <div class="dg-wrap">
        @if($guide->isFailed())
            {{-- ============ HATA DURUMU ============ --}}
            <div class="dg-panel" style="text-align:center;padding:60px 24px;">
                <div style="font-size:56px;margin-bottom:14px;">😔</div>
                <h1 style="font-size:22px;font-weight:800;color:var(--text);margin-bottom:8px;">Rehber oluşturulamadı</h1>
                <p style="color:var(--text-sec);font-size:14px;max-width:380px;margin:0 auto 22px;">
                    {{ $guide->error_message ?: 'Beklenmedik bir sorun oluştu.' }}
                </p>
                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" id="dg-retry" class="btn btn-primary">🔄 Tekrar dene</button>
                    <a href="{{ route('discovery.index') }}" class="btn btn-outline">Yeni rehber oluştur</a>
                </div>
            </div>
        @elseif(! $guide->isCompleted())
            {{-- ============ YÜKLEME DURUMU (pending/processing) ============ --}}
            <div class="dg-panel" style="text-align:center;padding:70px 24px;">
                <div style="width:44px;height:44px;border:4px solid var(--border-light);border-top-color:var(--accent);border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 18px;"></div>
                <h1 style="font-size:20px;font-weight:800;color:var(--text);margin-bottom:8px;">
                    {{ $guide->destination_input }} rehberiniz hazırlanıyor...
                </h1>
                <p style="color:var(--text-sec);font-size:14px;max-width:400px;margin:0 auto;">
                    {{ $guide->duration_days }} günlük planınız oluşturuluyor. Bu işlem genellikle bir dakikadan kısa sürer; sayfa kendiliğinden güncellenecek.
                </p>
                <div id="dg-slow" style="display:none;margin-top:14px;">
                    <p style="color:var(--text-muted);font-size:13px;margin-bottom:10px;">
                        Normalden uzun sürüyor — sayfayı açık tutabilirsiniz, kontrol aralıklarla devam ediyor.
                    </p>
                    <button type="button" class="btn btn-outline" onclick="window.location.reload()">Sayfayı yenile</button>
                </div>
            </div>
        @else
            @php($p = $guide->guide_payload)
            {{-- ============ TAMAMLANMIŞ REHBER ============ --}}
            <div class="dg-panel">
                <h1 style="font-size:26px;font-weight:800;color:var(--text);">
                    {{ $p['destination']['name'] }}
                    @if(!empty($p['destination']['country']))
                        <span class="badge badge-accent" style="vertical-align:middle;margin-left:6px;">{{ $p['destination']['country'] }}</span>
                    @endif
                </h1>
                <p style="color:var(--text-sec);font-size:15px;line-height:1.6;margin-top:10px;">{{ $p['destination']['summary'] }}</p>

                @if(!empty($p['unknown_destination']))
                    <div class="alert alert-error" style="margin-top:14px;">
                        Bu destinasyonu tam olarak tanıyamadık — içerik sınırlı veya genel olabilir. Yazımı kontrol edip yeniden deneyebilirsiniz.
                    </div>
                @endif

                {{-- Varsayımlar + kişiselleştirme --}}
                <div style="margin-top:16px;padding-top:16px;border-top:1px dashed var(--border);">
                    <div class="dg-chips" style="margin-bottom:12px;">
                        @foreach($guide->assumptionChips() as $chip)
                            <span class="dg-chip">{{ $chip }}</span>
                        @endforeach
                    </div>
                    <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:8px;">Planı sana göre uyarlayalım mı?</div>
                    <div class="dg-chips" id="dg-traveler-btns">
                        <button type="button" class="dg-chip-btn {{ $guide->traveler_type === 'solo' ? 'on' : '' }}" data-traveler="solo">Yalnız seyahat ediyorum</button>
                        <button type="button" class="dg-chip-btn {{ $guide->traveler_type === 'couple' ? 'on' : '' }}" data-traveler="couple">Sevgilimle gidiyorum</button>
                        <button type="button" class="dg-chip-btn {{ $guide->traveler_type === 'friends' ? 'on' : '' }}" data-traveler="friends">Arkadaşlarımla gidiyorum</button>
                        <button type="button" class="dg-chip-btn {{ $guide->traveler_type === 'family' ? 'on' : '' }}" data-traveler="family">Ailemle gidiyorum</button>
                        <button type="button" class="dg-chip-btn {{ $guide->traveler_type === 'with_kids' ? 'on' : '' }}" data-traveler="with_kids">Çocuklarla gidiyorum</button>
                        <button type="button" class="dg-chip-btn" id="dg-regenerate" title="Aynı tercihlerle yeniden üret">🔄 Rehberi yeniden oluştur</button>
                    </div>
                    <div id="dg-action-error" style="display:none;color:#b91c1c;font-size:13px;margin-top:10px;"></div>
                </div>
            </div>

            {{-- Gün gün program --}}
            <h2 class="dg-h2">🗓️ Gün Gün Gezi Programı</h2>
            @foreach($p['daily_plan'] as $day)
                <div class="dg-day">
                    <div class="dg-day-head">
                        <h3>{{ $day['day'] }}. Gün — {{ $day['title'] }}</h3>
                        @if(!empty($day['theme']))<p>{{ $day['theme'] }}</p>@endif
                    </div>
                    <div class="dg-day-body dg-kapali" id="dg-gun-{{ $loop->index }}">
                        @foreach(['morning' => '🌅 Sabah', 'afternoon' => '☀️ Öğleden Sonra', 'evening' => '🌙 Akşam'] as $anahtar => $baslik)
                            @if(!empty($day[$anahtar]))
                                <div class="dg-period">
                                    <div class="dg-period-title">{{ $baslik }}</div>
                                    @foreach($day[$anahtar] as $yer)
                                        <div class="dg-place">
                                            <div class="dg-place-name">
                                                {{ $yer['name'] }}
                                                @if(!empty($yer['suggested_duration']))<span class="dg-mini">⏱ {{ $yer['suggested_duration'] }}</span>@endif
                                            </div>
                                            @if(!empty($yer['description']))<p>{{ $yer['description'] }}</p>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                        @if(!empty($day['foods_to_try']))
                            <div class="dg-period">
                                <div class="dg-period-title">🍽️ Bugün Dene</div>
                                <div class="dg-chips">
                                    @foreach($day['foods_to_try'] as $yemek)
                                        <span class="dg-chip">{{ $yemek }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if(!empty($day['daily_tip']))
                            <div class="dg-tip">💡 {{ $day['daily_tip'] }}</div>
                        @endif
                    </div>
                    <button type="button" class="dg-day-ac" aria-expanded="false" aria-controls="dg-gun-{{ $loop->index }}">
                        <span class="dg-ac-yazi">Günün tamamını gör</span><span class="dg-ok" aria-hidden="true">▾</span>
                    </button>
                </div>
            @endforeach

            {{-- Gezilecek yerler --}}
            @if(!empty($p['highlights']))
                <h2 class="dg-h2">📍 Gezilecek Yerler</h2>
                <div class="dg-grid">
                    @foreach($p['highlights'] as $yer)
                        <div class="dg-item">
                            <b>{{ $yer['name'] }}</b>
                            @if(!empty($yer['description']))<p>{{ $yer['description'] }}</p>@endif
                            @if(!empty($yer['why_visit']))<div class="dg-why">✔ {{ $yer['why_visit'] }}</div>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Yapılacak aktiviteler --}}
            @if(!empty($p['things_to_do']))
                <h2 class="dg-h2">🎯 Yapılacak Aktiviteler</h2>
                <div class="dg-grid">
                    @foreach($p['things_to_do'] as $aktivite)
                        <div class="dg-item">
                            <b>{{ $aktivite['name'] }}</b>
                            @if(!empty($aktivite['description']))<p>{{ $aktivite['description'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Yerel yemekler --}}
            @if(!empty($p['local_foods']))
                <h2 class="dg-h2">🍴 Yerel Yemekler</h2>
                <div class="dg-grid">
                    @foreach($p['local_foods'] as $yemek)
                        <div class="dg-item">
                            <b>{{ $yemek['name'] }}</b>
                            @if(!empty($yemek['when_to_try']))<span class="dg-chip" style="margin-left:6px;">{{ $yemek['when_to_try'] }}</span>@endif
                            @if(!empty($yemek['description']))<p>{{ $yemek['description'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Tarihî alanlar --}}
            @if(!empty($p['historical_places']))
                <h2 class="dg-h2">🏛️ Tarihî Alanlar</h2>
                <div class="dg-grid">
                    @foreach($p['historical_places'] as $yer)
                        <div class="dg-item">
                            <b>{{ $yer['name'] }}</b>
                            @if(!empty($yer['description']))<p>{{ $yer['description'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Müzeler --}}
            @if(!empty($p['museums']))
                <h2 class="dg-h2">🖼️ Müzeler</h2>
                <div class="dg-grid">
                    @foreach($p['museums'] as $muze)
                        <div class="dg-item">
                            <b>{{ $muze['name'] }}</b>
                            @if(!empty($muze['description']))<p>{{ $muze['description'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Seyahat tavsiyeleri --}}
            @if(!empty($p['travel_tips']))
                <h2 class="dg-h2">🧳 Gitmeden Önce Bilinmesi Gerekenler</h2>
                <div class="dg-panel">
                    <ul class="dg-tips" style="list-style:disc;padding-left:20px;margin:0;">
                        @foreach($p['travel_tips'] as $tavsiye)
                            <li>{{ $tavsiye }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>

    {{-- İlgili turXtur turları (yalnız veritabanından) --}}
    @if($guide->isCompleted())
        <div style="max-width:1280px;margin:36px auto 0;">
            <h2 class="dg-h2">🧭 Bu destinasyondaki turları keşfet</h2>
            @if($relatedTours->isNotEmpty())
                @include('partials.tour_grid', ['popularTours' => $relatedTours])
            @else
                <div style="text-align:center;padding:40px;background:var(--white);border-radius:var(--radius-lg);border:1px dashed #cbd5e1;color:var(--text-muted);">
                    <div style="font-size:40px;margin-bottom:10px;">🧳</div>
                    <p style="margin-bottom:16px;">Bu destinasyon için şu anda yayında bir tur bulunmuyor.</p>
                    <a href="{{ route('tours.index') }}" class="btn btn-outline">Tüm turlara göz at</a>
                </div>
            @endif
        </div>
    @endif
</div>

<script>
(function () {
    const csrfToken = @json(csrf_token());
    const statusUrl = @json(route('discovery.status', $guide));
    const personalizeUrl = @json(route('discovery.personalize', $guide));
    const guideStatus = @json($guide->status);

    // ---- Polling: yalnız üretim sürerken. İlk 3 dk 3 sn'de bir, sonrasında
    // 15 sn'ye yavaşlar; sunucu 10 dk kıpırdamayan kaydı failed'a çevirdiği
    // için döngü fiilen orada sonlanır (reload → hata ekranı). 30 dk mutlak
    // tavan yalnız emniyet içindir.
    if (guideStatus === 'pending' || guideStatus === 'processing') {
        const baslangic = Date.now();
        async function durumaBak() {
            const gecen = Date.now() - baslangic;
            if (gecen > 30 * 60 * 1000) return;
            if (gecen > 60 * 1000) {
                const yavas = document.getElementById('dg-slow');
                if (yavas) yavas.style.display = 'block';
            }
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    if (data.status === 'completed' || data.status === 'failed') {
                        window.location.reload();
                        return;
                    }
                }
            } catch (e) { /* geçici ağ hatası — sonraki denemede tekrar */ }
            setTimeout(durumaBak, gecen > 3 * 60 * 1000 ? 15000 : 3000);
        }
        setTimeout(durumaBak, 3000);
    }

    // ---- Kişiselleştir / tekrar dene / yeniden oluştur ----
    async function personalize(payload, btn) {
        const hataKutusu = document.getElementById('dg-action-error');
        if (hataKutusu) hataKutusu.style.display = 'none';
        if (btn) { btn.disabled = true; btn.textContent = 'Hazırlanıyor...'; }
        try {
            const res = await fetch(personalizeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                throw new Error(res.status === 429
                    ? 'Biraz hızlı gidiyoruz 🙂 Birkaç saniye bekleyip tekrar dener misin?'
                    : 'Sunucuda bir sorun oluştu — lütfen tekrar dene.');
            }
            window.location.reload();
        } catch (err) {
            if (btn) { btn.disabled = false; }
            if (hataKutusu) { hataKutusu.textContent = err.message; hataKutusu.style.display = 'block'; }
            else alert(err.message);
        }
    }

    document.querySelectorAll('#dg-traveler-btns [data-traveler]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            personalize({ traveler_type: btn.dataset.traveler }, btn);
        });
    });

    const yenidenBtn = document.getElementById('dg-regenerate');
    if (yenidenBtn) yenidenBtn.addEventListener('click', function () { personalize({}, yenidenBtn); });

    const tekrarBtn = document.getElementById('dg-retry');
    if (tekrarBtn) tekrarBtn.addEventListener('click', function () { personalize({}, tekrarBtn); });

    // ---- Gün kartları: kapalı gelir, kullanıcı açar. İçerik zaten sınırın
    // altındaysa (kısa gün) düğme ölü buton olmasın diye kaldırılır.
    document.querySelectorAll('.dg-day-ac').forEach(function (btn) {
        const govde = document.getElementById(btn.getAttribute('aria-controls'));
        if (!govde) { btn.remove(); return; }

        if (govde.scrollHeight <= govde.clientHeight + 8) {
            govde.classList.remove('dg-kapali');
            btn.remove();
            return;
        }

        btn.addEventListener('click', function () {
            const acik = ! govde.classList.toggle('dg-kapali');
            btn.setAttribute('aria-expanded', acik ? 'true' : 'false');
            btn.querySelector('.dg-ac-yazi').textContent = acik ? 'Daha az göster' : 'Günün tamamını gör';
            // Kapanırken kart ekranın dışında kalmasın (uzun günlerde sayfa zıplaması).
            if (! acik) btn.scrollIntoView({ block: 'nearest' });
        });
    });
})();
</script>
@endsection
