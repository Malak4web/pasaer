<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>تسجيل الدخول · لوحة تحكم بصائر</title>
    <link rel="icon" href="{{ asset('assets/admin/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&family=Amiri&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/admin/admin.css') }}?v={{ filemtime(public_path('assets/admin/admin.css')) }}">
</head>
<body>
<main class="auth">
    <div class="card auth-card">
        <div class="auth-head">
            <x-brand-mark />
            <h1>لوحة تحكم بصائر</h1>
            <p>سجّل دخولك لإدارة محتوى الموقع.</p>
        </div>

        <form method="POST" action="{{ route('admin.login.attempt') }}" class="fields">
            @csrf
            @error('email')
                <div class="alert alert-error" role="alert"><i class="ti ti-alert-circle" aria-hidden="true"></i> {{ $message }}</div>
            @enderror

            <div class="field">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" class="ltr" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" class="ltr" required autocomplete="current-password">
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> تذكّرني على هذا الجهاز</label>
            <button class="btn btn-primary" style="height:44px">تسجيل الدخول</button>
        </form>

        <p class="verse">﴿{{ \App\Models\Setting::get('verse_text') }}﴾</p>
    </div>
</main>
</body>
</html>
