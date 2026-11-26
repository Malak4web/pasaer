@php
    $drafts = fn ($model) => $model::where('status', 'draft')->count() ?: null;
    $nav = [
        ['admin.dashboard', 'الرئيسية', 'ti-layout-dashboard', null],
        'المحتوى',
        ['admin.videos.index', 'المرئيات', 'ti-player-play', $drafts(\App\Models\Video::class)],
        ['admin.audios.index', 'الصوتيات', 'ti-headphones', $drafts(\App\Models\Audio::class)],
        ['admin.lessons.index', 'الدروس', 'ti-book', $drafts(\App\Models\Lesson::class)],
        ['admin.posts.index', 'المنشورات', 'ti-notes', $drafts(\App\Models\Post::class)],
        'التنظيم',
        ['admin.audios.organize', 'تصنيف الصوتيات', 'ti-arrows-move', null],
        ['admin.series.index', 'السلاسل', 'ti-stack-2', null],
        ['admin.topics.index', 'المواضيع', 'ti-category', null],
    ];
    if (auth()->user()->isAdmin()) {
        $nav[] = 'الإدارة';
        $nav[] = ['admin.users.index', 'المستخدمون', 'ti-users', null];
        $nav[] = ['admin.settings', 'إعدادات الموقع', 'ti-settings', null];
    }
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · لوحة تحكم بصائر</title>
    <link rel="icon" href="{{ asset('assets/admin/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/admin/admin.css') }}?v={{ filemtime(public_path('assets/admin/admin.css')) }}">
</head>
<body>
<div class="shell">
    <aside class="side" aria-label="القائمة">
        <a href="{{ route('admin.dashboard') }}" class="brand">
            <x-brand-mark /> بصائر <small>التحكم</small>
        </a>
        <nav class="nav">
            @foreach ($nav as $item)
                @if (is_string($item))
                    <span class="nav-label">{{ $item }}</span>
                @else
                    @php
                        [$route, $label, $icon, $count] = $item;
                        // admin.videos.index ينوّر في كل صفحات admin.videos.* ، والباقي مطابقة تامة
                        $pattern = str_ends_with($route, '.index') ? \Illuminate\Support\Str::beforeLast($route, '.').'.*' : $route.'*';
                    @endphp
                    <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])>
                        <i class="ti {{ $icon }}" aria-hidden="true"></i> {{ $label }}
                        @if ($count)<span class="count" title="مسودات">{{ $count }}</span>@endif
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="side-foot">
            <a href="{{ config('app.frontend_url') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                <i class="ti ti-external-link" aria-hidden="true"></i> عرض الموقع
            </a>
            <div class="me">
                <span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <a href="{{ route('admin.account') }}" class="me-info">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ \App\Models\User::ROLES[auth()->user()->role] ?? '' }} · حسابي</span>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="icon-btn" aria-label="تسجيل الخروج" title="تسجيل الخروج"><i class="ti ti-logout"></i></button>
                </form>
            </div>
        </div>
    </aside>
    <div class="backdrop" data-toggle-nav></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" data-toggle-nav aria-label="القائمة"><i class="ti ti-menu-2"></i></button>
            <nav class="crumbs" aria-label="المسار">
                <a href="{{ route('admin.dashboard') }}">لوحة التحكم</a>
                @hasSection('crumb')
                    <i class="ti ti-chevron-left" aria-hidden="true"></i> @yield('crumb')
                @endif
            </nav>
            <span class="spacer"></span>
            @yield('topbar')
        </header>

        <main class="content">
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <i class="ti ti-alert-circle" aria-hidden="true"></i>
                    <div>
                        <strong>راجع الحقول دي:</strong>
                        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success toast" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
@endif

<script src="{{ asset('assets/admin/admin.js') }}?v={{ filemtime(public_path('assets/admin/admin.js')) }}"></script>
</body>
</html>
