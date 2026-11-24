@extends('admin.layout')
@section('title', 'السلاسل')
@section('crumb', 'السلاسل')

@section('content')
<div class="page-head">
    <div><h1>السلاسل</h1><p>تجميع الدروس المتتابعة. أضف المقاطع والصوتيات والدروس للسلسلة من شاشة كل عنصر.</p></div>
    <a href="{{ route('admin.series.create') }}" class="btn btn-primary"><i class="ti ti-plus" aria-hidden="true"></i> سلسلة جديدة</a>
</div>

<div class="card">
    <form method="GET" class="toolbar" role="search">
        <div class="search"><i class="ti ti-search" aria-hidden="true"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ابحث باسم السلسلة…" aria-label="بحث"></div>
        <select name="topic" aria-label="الموضوع">
            <option value="">كل المواضيع</option>
            @foreach ($topics as $t)<option value="{{ $t->id }}" @selected(($filters['topic'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <button class="btn btn-secondary btn-sm">بحث</button>
    </form>
    @if ($items->isEmpty())
        <div class="empty"><i class="ti ti-stack-2" aria-hidden="true"></i> لسه ما فيش سلاسل.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>السلسلة</th><th>النوع</th><th>الموضوع</th><th>العناصر</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                @foreach ($items as $s)
                    <tr>
                        <td class="title-cell">
                            <a href="{{ route('admin.series.edit', $s) }}">{{ $s->title }}</a>
                            @if ($s->is_featured)<small><i class="ti ti-star" aria-hidden="true"></i> مميزة في الرئيسية</small>@endif
                        </td>
                        <td><span class="badge badge-soft">{{ \App\Models\Series::KINDS[$s->kind] ?? $s->kind }}</span></td>
                        <td>{{ $s->topic?->name ?? '—' }}</td>
                        <td class="num">{{ $s->videos_count + $s->audios_count + $s->lessons_count }}</td>
                        <td><span class="badge badge-{{ $s->status }}">{{ $s->status === 'published' ? 'منشورة' : 'مسودة' }}</span></td>
                        <td class="row-actions">
                            <a class="icon-btn" href="{{ route('admin.series.edit', $s) }}" title="تعديل" aria-label="تعديل"><i class="ti ti-edit"></i></a>
                            <x-delete-form :action="route('admin.series.destroy', $s)" label="السلسلة «{{ $s->title }}» (العناصر نفسها مش هتتحذف)" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$items" />
    @endif
</div>
@endsection
