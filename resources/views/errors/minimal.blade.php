{{-- turXtur markalı hata sayfası — KENDİNE YETER (app layout'a bağlı değil ki
     500'de layout render edilemese bile çalışsın). @yield ile kod/başlık/mesaj. --}}
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Bir sorun oluştu') — turXtur</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #0c332e;
            color: #e6f2ee;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box { text-align: center; max-width: 460px; }
        .code {
            font-size: 84px;
            font-weight: 800;
            line-height: 1;
            color: #5eead4;
            letter-spacing: -2px;
        }
        .title { font-size: 22px; font-weight: 700; margin: 14px 0 8px; color: #ffffff; }
        .msg { font-size: 15px; line-height: 1.6; color: #a7c4bc; margin-bottom: 28px; }
        .btn {
            display: inline-block;
            background: #5eead4;
            color: #0c332e;
            font-weight: 700;
            font-size: 15px;
            padding: 12px 26px;
            border-radius: 100px;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(94,234,212,.25); }
        .brand { margin-bottom: 32px; font-size: 26px; font-weight: 800; letter-spacing: -.5px; }
        .brand span { color: #5eead4; }
    </style>
</head>
<body>
    <div class="box">
        <div class="brand">tur<span>X</span>tur</div>
        <div class="code">@yield('code')</div>
        <div class="title">@yield('title', 'Bir sorun oluştu')</div>
        <div class="msg">@yield('message', 'Beklenmedik bir durum oluştu. Lütfen tekrar deneyin.')</div>
        <a href="{{ url('/') }}" class="btn">Ana sayfaya dön</a>
    </div>
</body>
</html>
