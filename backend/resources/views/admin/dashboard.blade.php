@extends('admin.layout')
@section('title', 'الرئيسية')

@section('content')
<div class="page-head">
    <div>
        <h1>أهلًا، {{ auth()->user()->name }}</h1>
        <p>نظرة سريعة على محتوى الموقع.</p>
    </div>
    <div class="actions">
        <a href="{{ route('admin.videos.create') }}" class="btn btn-primary"><i class="ti ti-plus" aria-hidden="true"></i> مقطع جديد</a>
        <a href="{{ route('admin.audios.create') }}" class="btn btn-secondary"><i class="ti ti-upload" aria-hidden="true"></i> رفع صوتية</a>
        <a href="{{ route('admin.posts.create') }}" class="btn btn-secondary"><i class="ti ti-pencil" aria-hidden="true"></i> منشور</a>
    </div>
</div>

<section class="stats" aria-label="إحصائيات">
    @foreach ($stats as $key => $s)
        <a href="{{ route("admin.{$key}.index") }}" class="card stat">
            <span class="stat-top">{{ $s['label'] }} <i class="ti {{ $s['icon'] }}" aria-hidden="true"></i></span>
            <span class="stat-num">{{ number_format($s['total']) }}</span>
            <span class="stat-sub">
                @if ($s['drafts'])<span class="warn">{{ $s['drafts'] }} مسودة</span>@else كله منشور @endif
            </span>
        </a>
    @endforeach
</section>

<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>آخر التعديلات</h2></div>
        @if ($recent->isEmpty())
            <div class="empty"><i class="ti ti-mood-empty" aria-hidden="true"></i> لسه ما فيش محتوى. ابدأ بإضافة أول مقطع.</div>
        @else
            <ul class="rows">
                @foreach ($recent as $r)
                    <li>
                        <span class="ico" aria-hidden="true"><i class="ti {{ $r['icon'] }}"></i></span>
                        <div class="grow">
                            <a href="{{ route("admin.{$r['resource']}.edit", $r['item']) }}">{{ $r['item']->title }}</a>
                            <span>{{ $r['label'] }} · {{ $r['item']->topic?->name ?? 'بدون موضوع' }} · {{ $r['item']->updated_at->diffForHumans() }}</span>
                        </div>
                        <x-status :item="$r['item']" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div style="display:flex;flex-direction:column;gap:20px">
        <section class="card card-pad" style="display:flex;flex-direction:column;gap:14px">
            <div class="stat-top">إجمالي المشاهدات المسجّلة <i class="ti ti-eye" aria-hidden="true"></i></div>
            <span class="stat-num">{{ number_format($totalViews) }}</span>
            <div class="stat-top" style="border-top:1px solid var(--border);padding-top:14px">السلاسل <i class="ti ti-stack-2" aria-hidden="true"></i></div>
            <span class="stat-num">{{ $seriesCount }}</span>
        </section>

        <section class="card">
            <div class="card-head"><h2>مجدول للنشر</h2></div>
            @if ($scheduled->isEmpty())
                <p class="muted" style="padding:16px 20px;font-size:14px">ما فيش محتوى مجدول. تقدر تحدد تاريخ نشر لاحق من شاشة التعديل.</p>
            @else
                <ul class="rows">
                    @foreach ($scheduled as $r)
                        <li>
                            <div class="grow">
                                <a href="{{ route("admin.{$r['resource']}.edit", $r['item']) }}">{{ $r['item']->title }}</a>
                                <span>{{ $r['label'] }} · {{ $r['item']->published_at->translatedFormat('j F، H:i') }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
@endsection
