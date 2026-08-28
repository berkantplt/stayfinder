@extends('layouts.app')
@section('title', 'AI Keşif Rehberi — turXtur')
@section('description', 'Şehri ve kaç gün kalacağınızı yazın; yapay zekâ destekli, gün gün gezilecek yerler, yemekler ve tavsiyelerle dolu kişisel keşif rehberiniz hazırlansın.')

@section('content')
<style>
    .dg-wrap { max-width: 720px; margin: 0 auto; }
    .dg-hero { text-align: center; padding: 8px 0 28px; }
    .dg-hero h1 { font-size: 30px; font-weight: 800; color: var(--text); margin-bottom: 10px; }
    .dg-hero p { color: var(--text-sec); font-size: 15px; max-width: 480px; margin: 0 auto; }
    .dg-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); padding: 28px; }
    .dg-required { display: grid; grid-template-columns: 1fr 160px; gap: 14px; }
    @media (max-width: 560px) { .dg-required { grid-template-columns: 1fr; } .dg-hero h1 { font-size: 24px; } .dg-card { padding: 20px; } }
    .dg-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .dg-chip { border: 1px solid var(--border); background: var(--white); color: var(--text-sec); border-radius: 999px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .15s; }
    .dg-chip:hover { border-color: var(--accent); color: var(--accent-dark); }
    .dg-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .dg-details { margin-top: 18px; border-top: 1px dashed var(--border); padding-top: 4px; }
    .dg-details summary { cursor: pointer; list-style: none; padding: 12px 0; font-weight: 700; font-size: 14px; color: var(--accent-dark); display: flex; align-items: center; gap: 8px; }
    .dg-details summary::-webkit-details-marker { display: none; }
    .dg-details summary::after { content: '▾'; font-size: 12px; transition: transform .15s; }
    .dg-details[open] summary::after { transform: rotate(180deg); }
    .dg-opt-label { font-size: 13px; font-weight: 700; color: var(--text); margin: 14px 0 8px; }
    .dg-error { display: none; margin-top: 14px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: var(--radius); padding: 12px 14px; font-size: 14px; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<div class="container" style="padding-top:40px;padding-bottom:60px;">
    @include('partials.breadcrumb', ['items' => [['name' => 'Keşif Rehberi']]])

    <div class="dg-wrap">
        <div class="dg-hero">
            <h1>🧭 AI Keşif Rehberi</h1>
            <p>Nereye gideceğinizi ve kaç gün kalacağınızı yazın — gün gün gezilecek yerler, yerel lezzetler ve tavsiyelerle dolu kişisel rehberiniz dakikalar içinde hazır olsun.</p>
        </div>

        <div class="dg-card">
            <form id="dg-form" novalidate>
                <div class="dg-required">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="dg-destination">Destinasyon</label>
                        <input type="text" id="dg-destination" name="destination" maxlength="100"
                            placeholder="Örn. Paris, Roma, Kapadokya..." autocomplete="off" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="dg-days">Kaç gün?</label>
                        <select id="dg-days" name="duration_days" required>
                            @foreach(range(1, 7) as $gun)
                                <option value="{{ $gun }}" @selected($gun === 4)>{{ $gun }} gün</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <details class="dg-details" id="dg-personalize">
                    <summary>✨ Planı kişiselleştir <span style="font-weight:500;color:var(--text-muted);">(isteğe bağlı)</span></summary>

                    <div class="dg-opt-label">Kimlerle seyahat ediyorsun?</div>
                    <div class="dg-chips" data-group="traveler_type" data-single="1">
                        @foreach(\App\Models\DiscoveryGuide::TRAVELER_TYPES as $deger => $etiket)
                            <button type="button" class="dg-chip" data-value="{{ $deger }}">{{ $etiket }}</button>
                        @endforeach
                    </div>

                    <div class="dg-opt-label">İlgi alanları</div>
                    <div class="dg-chips" data-group="interests">
                        @foreach(\App\Models\DiscoveryGuide::INTERESTS as $deger => $etiket)
                            <button type="button" class="dg-chip" data-value="{{ $deger }}">{{ $etiket }}</button>
                        @endforeach
                    </div>

                    <div class="dg-opt-label">Tempo</div>
                    <div class="dg-chips" data-group="pace" data-single="1">
                        @foreach(\App\Models\DiscoveryGuide::PACES as $deger => $etiket)
                            <button type="button" class="dg-chip {{ $deger === 'normal' ? 'on' : '' }}" data-value="{{ $deger }}">{{ $etiket }}</button>
                        @endforeach
                    </div>

                    <div class="dg-opt-label">Bütçe</div>
                    <div class="dg-chips" data-group="budget" data-single="1">
                        @foreach(\App\Models\DiscoveryGuide::BUDGETS as $deger => $etiket)
                            <button type="button" class="dg-chip {{ $deger === 'standard' ? 'on' : '' }}" data-value="{{ $deger }}">{{ $etiket }}</button>
                        @endforeach
                    </div>
                </details>

                <div id="dg-error" class="dg-error" role="alert"></div>

                <button type="submit" id="dg-submit" class="btn btn-primary" style="width:100%;margin-top:18px;padding:14px;font-size:15px;">
                    Rehberimi Oluştur
                </button>
                <p style="text-align:center;color:var(--text-muted);font-size:12px;margin-top:10px;">
                    Bu bir içerik rehberidir; harita rotası veya navigasyon içermez.
                </p>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const csrfToken = @json(csrf_token());
    const storeUrl = @json(route('discovery.store'));
    const form = document.getElementById('dg-form');
    const submitBtn = document.getElementById('dg-submit');
    const errorBox = document.getElementById('dg-error');

    // Çip grupları: data-single olanlar radyo gibi (tekrar tıklayınca bırakır),
    // diğerleri çoklu seçim.
    document.querySelectorAll('.dg-chips').forEach(function (group) {
        group.addEventListener('click', function (e) {
            const chip = e.target.closest('.dg-chip');
            if (!chip) return;
            if (group.dataset.single) {
                const wasOn = chip.classList.contains('on');
                group.querySelectorAll('.dg-chip').forEach(c => c.classList.remove('on'));
                if (!wasOn) chip.classList.add('on');
            } else {
                chip.classList.toggle('on');
            }
        });
    });

    function selectedSingle(groupName) {
        const on = document.querySelector('.dg-chips[data-group="' + groupName + '"] .dg-chip.on');
        return on ? on.dataset.value : null;
    }

    function selectedMulti(groupName) {
        return Array.from(document.querySelectorAll('.dg-chips[data-group="' + groupName + '"] .dg-chip.on'))
            .map(c => c.dataset.value);
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.style.display = 'block';
    }

    function friendlyHttpError(status) {
        if (status === 429) return 'Biraz hızlı gidiyoruz 🙂 Birkaç saniye bekleyip tekrar dener misin?';
        if (status === 419) return 'Oturumun yenilenmiş — sayfayı yenileyip (F5) tekrar dener misin?';
        return 'Sunucuda bir sorun oluştu (HTTP ' + status + ') — lütfen tekrar dene.';
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errorBox.style.display = 'none';

        const destination = document.getElementById('dg-destination').value.trim();
        if (destination.length < 2) {
            showError('Lütfen bir şehir veya ilçe adı yazın.');
            return;
        }

        const payload = {
            destination: destination,
            duration_days: parseInt(document.getElementById('dg-days').value, 10),
            traveler_type: selectedSingle('traveler_type'),
            interests: selectedMulti('interests'),
            pace: selectedSingle('pace') || 'normal',
            budget: selectedSingle('budget') || 'standard',
        };

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite;vertical-align:-3px;margin-right:8px;"></span>Rehberiniz hazırlanıyor...';

        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });

            if (res.status === 422) {
                const data = await res.json().catch(() => ({}));
                const first = data.errors ? Object.values(data.errors)[0] : null;
                throw new Error(first && first[0] ? first[0] : 'Girdiğiniz bilgileri kontrol eder misiniz?');
            }
            if (!res.ok) throw new Error(friendlyHttpError(res.status));

            const data = await res.json();
            window.location.assign(data.redirect_url);
        } catch (err) {
            showError(err.message || 'Beklenmedik bir sorun oluştu — lütfen tekrar dene.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Rehberimi Oluştur';
        }
    });
})();
</script>
@endsection
