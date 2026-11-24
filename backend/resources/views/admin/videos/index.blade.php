@extends('admin.layout')
@section('title', 'المرئيات')
@section('crumb', 'المرئيات')

@section('content')
<div class="page-head">
    <div><h1>المرئيات</h1><p>مقاطع يوتيوب المعروضة في الموقع.</p></div>
    <a href="{{ route('admin.videos.create') }}" class="btn btn-primary"><i class="ti ti-plus" aria-hidden="true"></i> إضافة مقطع</a>
</div>

<div class="card">
    <x-filters :filters="$filters" :topics="$topics" :series-list="$seriesList" />
    @if ($items->isEmpty())
        <div class="empty"><i class="ti ti-video-off" aria-hidden="true"></i>
            @if (array_filter($filters)) ما فيش نتايج للفلاتر دي. @else لسه ما فيش مقاطع. الصق رابط يوتيوب وابدأ. @endif
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th class="hide-sm"></th><th>العنوان</th><th>الموضوع</th><th class="hide-sm">المدة</th><th class="hide-sm">المشاهدات</th><th>الحالة</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($items as $v)
                    <tr>
                        <td class="hide-sm">
                            @if ($v->youtube_id)
                                <img class="thumb" src="https://i.ytimg.com/vi/{{ $v->youtube_id }}/default.jpg" alt="" loading="lazy">
                            @else
                                <span class="thumb"><i class="ti ti-player-play" aria-hidden="true"></i></span>
                            @endif
                        </td>
                        <td class="title-cell">
                            <a href="{{ route('admin.videos.edit', $v) }}">{{ $v->title }}</a>
                            <small>
                                @if ($v->series) {{ $v->series->title }} #{{ $v->series_order }} · @endif
                                {{ $v->published_at?->translatedFormat('j F Y') ?? 'غير منشور' }}
                                @unless ($v->youtube_id) · <span style="color:var(--warning)">بدون رابط يوتيوب</span> @endunless
                            </small>
                        </td>
                        <td>{{ $v->topic?->name ?? '—' }}</td>
                        <td class="num hide-sm">{{ \App\Support\Arabic::formatDuration($v->duration) }}</td>
                        <td class="num hide-sm">{{ number_format($v->views) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.toggle', ['videos', $v->id]) }}">@csrf
                                <button class="status-toggle" title="اضغط للتبديل بين النشر والمسودة"><x-status :item="$v" /></button>
                            </form>
                        </td>
                        <td class="row-actions">
                            @if ($v->youtube_id)
                                <a class="icon-btn" href="https://www.youtube.com/watch?v={{ $v->youtube_id }}" target="_blank" rel="noopener" title="فتح على يوتيوب" aria-label="فتح على يوتيوب"><i class="ti ti-brand-youtube"></i></a>
                            @endif
                            <a class="icon-btn" href="{{ route('admin.videos.edit', $v) }}" title="تعديل" aria-label="تعديل"><i class="ti ti-edit"></i></a>
                            <x-delete-form :action="route('admin.videos.destroy', $v)" label="المقطع «{{ $v->title }}»" />
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
