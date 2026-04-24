@extends('layouts.app')
@section('title', 'Kayıt Ol — StayFinder')

@section('content')
@include('partials.auth-background')

@php($selectedType = old('account_type', request('type') === 'agency' ? 'agency' : 'visitor'))

<div class="container" style="max-width:560px;padding-top:80px;padding-bottom:100px;position:relative;z-index:10;">
    <div style="background:rgba(255,255,255,0.95);backdrop-filter:blur(16px);border-radius:24px;padding:48px 40px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5);">

        <div style="text-align:center;margin-bottom:28px;">
            <h1 style="font-size:28px;font-weight:800;color:#0f172a;margin-bottom:8px;">Kayıt Ol</h1>
            <p id="register-subtitle" style="color:#475569;font-size:15px;">
                {{ $selectedType === 'agency' ? 'Acenta başvurunuzu oluşturun, admin onayı sonrası paneliniz aktif olsun' : 'Ücretsiz hesap oluşturun' }}
            </p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:28px;">
            <button type="button" class="account-switch {{ $selectedType === 'visitor' ? 'active' : '' }}" data-account-type="visitor">
                <span style="font-size:18px;">🙋</span>
                <span>
                    <strong style="display:block;">Bireysel</strong>
                    <span style="font-size:12px;opacity:.8;">Favori ve fiyat alarmı</span>
                </span>
            </button>
            <button type="button" class="account-switch {{ $selectedType === 'agency' ? 'active' : '' }}" data-account-type="agency">
                <span style="font-size:18px;">🏢</span>
                <span>
                    <strong style="display:block;">Acenta</strong>
                    <span style="font-size:12px;opacity:.8;">Tur ve kategori yönetimi</span>
                </span>
            </button>
        </div>

        @if($errors->any())
            <div class="alert alert-error" style="margin-bottom:24px;">
                @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register.post') }}" id="register-form">
            @csrf
            <input type="hidden" name="account_type" id="account_type" value="{{ $selectedType }}">

            <div id="agency-fields" style="display:{{ $selectedType === 'agency' ? 'block' : 'none' }};">
                <div style="padding:14px 16px;border-radius:16px;background:#f0fdfa;border:1px solid #99f6e4;color:#0f766e;font-size:13px;line-height:1.6;margin-bottom:20px;">
                    Başvuru gönderildiğinde sizin için bir acenta profili ve yetkili kullanıcı hesabı oluşturulur. Admin onayı sonrası kategori satın alıp tur paylaşmaya başlayabilirsiniz.
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Acenta Adı</label>
                    <input type="text" name="agency_name" value="{{ old('agency_name') }}" data-agency-required="true" style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label id="name-label" style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">
                    {{ $selectedType === 'agency' ? 'Yetkili Ad Soyad' : 'Ad Soyad' }}
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label id="email-label" style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">
                    {{ $selectedType === 'agency' ? 'Yetkili E-posta' : 'E-posta' }}
                </label>
                <input type="email" name="email" value="{{ old('email') }}" required style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>

            <div id="agency-meta-fields" style="display:{{ $selectedType === 'agency' ? 'block' : 'none' }};">
                <div class="form-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
                    <div class="form-group" style="margin-bottom:20px;">
                        <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Telefon</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
                    </div>
                    <div class="form-group" style="margin-bottom:20px;">
                        <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Web Sitesi</label>
                        <input type="url" name="website_url" value="{{ old('website_url') }}" placeholder="https://..." style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Kısa Açıklama</label>
                    <textarea name="description" rows="3" style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;resize:vertical;">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Şifre</label>
                <input type="password" name="password" required style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>

            <div class="form-group" style="margin-bottom:28px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Şifre Tekrar</label>
                <input type="password" name="password_confirmation" required style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>

            <button type="submit" id="submit-label" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:16px;font-weight:700;border-radius:12px;box-shadow:0 4px 12px rgba(13, 116, 144, 0.3);">
                {{ $selectedType === 'agency' ? 'Acenta Hesabı Oluştur' : 'Kayıt Ol' }}
            </button>
        </form>

        <div style="text-align:center;margin-top:28px;font-size:14px;color:#475569;">
            Zaten hesabınız var mı? <a href="{{ route('login') }}" style="color:var(--accent);font-weight:700;">Giriş Yap</a>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .account-switch {
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        border-radius: 16px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
        color: #475569;
        cursor: pointer;
        transition: all .2s ease;
    }

    .account-switch.active {
        background: linear-gradient(135deg, #0f766e, #0891b2);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 12px 24px rgba(8, 145, 178, 0.22);
    }

    @media (max-width: 640px) {
        .form-row {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const switches = document.querySelectorAll('.account-switch');
    const accountTypeInput = document.getElementById('account_type');
    const agencyFields = document.getElementById('agency-fields');
    const agencyMetaFields = document.getElementById('agency-meta-fields');
    const submitLabel = document.getElementById('submit-label');
    const nameLabel = document.getElementById('name-label');
    const emailLabel = document.getElementById('email-label');
    const subtitle = document.getElementById('register-subtitle');
    const agencyNameInput = document.querySelector('input[name="agency_name"]');
    const agencySpecificInputs = [
        agencyNameInput,
        document.querySelector('input[name="phone"]'),
        document.querySelector('input[name="website_url"]'),
        document.querySelector('textarea[name="description"]')
    ];

    function syncForm(type) {
        const isAgency = type === 'agency';

        accountTypeInput.value = type;
        agencyFields.style.display = isAgency ? 'block' : 'none';
        agencyMetaFields.style.display = isAgency ? 'block' : 'none';
        submitLabel.textContent = isAgency ? 'Acenta Hesabı Oluştur' : 'Kayıt Ol';
        nameLabel.textContent = isAgency ? 'Yetkili Ad Soyad' : 'Ad Soyad';
        emailLabel.textContent = isAgency ? 'Yetkili E-posta' : 'E-posta';
        subtitle.textContent = isAgency
            ? 'Acenta başvurunuzu oluşturun, admin onayı sonrası paneliniz aktif olsun'
            : 'Ücretsiz hesap oluşturun';

        switches.forEach((button) => {
            button.classList.toggle('active', button.dataset.accountType === type);
        });

        agencySpecificInputs.forEach((field) => {
            if (!field) {
                return;
            }

            const requiredForAgency = field.dataset.agencyRequired === 'true';
            field.disabled = !isAgency;
            field.required = isAgency && requiredForAgency;
        });
    }

    switches.forEach((button) => {
        button.addEventListener('click', function () {
            syncForm(this.dataset.accountType);
        });
    });

    syncForm(accountTypeInput.value || 'visitor');
});
</script>
@endpush
