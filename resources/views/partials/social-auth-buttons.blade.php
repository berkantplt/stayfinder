{{--
    Google / Apple giriş butonları. Giriş ve kayıt sayfasında ortak.

    Değişkenler:
      $mode      'login' | 'register'  (yalnız başlık metni için)
      $next      güvenli yerel yol (kalpten gelen ziyaretçi turuna dönsün)
      $favori    bekleyen favori tur id'si

    Anahtarı olmayan sağlayıcının butonu hiç basılmaz (bkz. SocialAuth::enabled);
    Apple anahtarları .env'e eklendiği an buton kendiliğinden görünür.
--}}
@php
    $socialProviders = \App\Support\SocialAuth::enabledProviders();
    $socialQuery = array_filter([
        'next' => \App\Support\LoginReturn::safePath($next ?? request('next')),
        'favori' => ctype_digit((string) ($favori ?? request('favori'))) ? ($favori ?? request('favori')) : null,
    ]);
@endphp

@if($socialProviders !== [])
<div class="social-auth" data-social-auth>
    <div class="social-auth__sep"><span>{{ ($mode ?? 'login') === 'register' ? 'veya şununla kayıt ol' : 'veya şununla devam et' }}</span></div>

    <div class="social-auth__buttons">
        @foreach($socialProviders as $provider)
            <a href="{{ route('social.redirect', array_merge(['provider' => $provider], $socialQuery)) }}"
               class="social-auth__btn social-auth__btn--{{ $provider }}"
               rel="nofollow">
                @if($provider === 'google')
                    {{-- Google'ın marka kılavuzu: logo renkleri değiştirilmez --}}
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                        <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
                        <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.83.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>
                        <path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>
                        <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.42 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
                    </svg>
                    <span>Google ile {{ ($mode ?? 'login') === 'register' ? 'kayıt ol' : 'devam et' }}</span>
                @else
                    <svg width="18" height="18" viewBox="0 0 384 512" aria-hidden="true" focusable="false" fill="currentColor">
                        <path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/>
                    </svg>
                    <span>Apple ile {{ ($mode ?? 'login') === 'register' ? 'kayıt ol' : 'devam et' }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>

<style>
.social-auth { margin-top:24px; }
.social-auth__sep { display:flex; align-items:center; gap:12px; margin-bottom:16px; color:#94a3b8; font-size:13px; }
.social-auth__sep::before, .social-auth__sep::after { content:""; flex:1; height:1px; background:#e2e8f0; }
.social-auth__buttons { display:grid; gap:10px; }
.social-auth__btn {
    display:flex; align-items:center; justify-content:center; gap:10px;
    padding:13px 16px; border-radius:12px; font-size:15px; font-weight:600;
    border:1px solid #cbd5e1; background:#fff; color:#1f2937;
    transition:background .15s, border-color .15s, box-shadow .15s;
}
.social-auth__btn:hover { background:#f8fafc; border-color:#94a3b8; box-shadow:0 2px 8px rgba(15,23,42,.08); }
.social-auth__btn:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
.social-auth__btn--apple { background:#000; border-color:#000; color:#fff; }
.social-auth__btn--apple:hover { background:#1a1a1a; border-color:#1a1a1a; }
</style>
@endif
