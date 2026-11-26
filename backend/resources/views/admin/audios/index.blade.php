@extends('admin.layout')
@section('title', 'الصوتيات')
@section('crumb', 'الصوتيات')

@section('content')
<div class="page-head">
    <div><h1>الصوتيات</h1><p>دروس ومحاضرات صوتية تُشغَّل من مشغّل الموقع.</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="{{ route('admin.audios.organize') }}" class="btn btn-secondary"><i class="ti ti-arrows-move" aria-hidden="true"></i> تصنيف بالسحب والإفلات</a>
        <a href="{{ route('admin.audios.create') }}" class="btn btn-primary"><i class="ti ti-upload" aria-hidden="true"></i> رفع صوتية</a>
    </div>
</div>

<div class="card">
    <x-filters :filters="$filters" :topics="$topics" :series-list="$seriesList" />
    @if ($items->isEmpty())
        <div class="empty"><i class="ti ti-headphones-off" aria-hidden="true"></i>
            @if (array_filter($filters)) ما فيش نتايج للفلاتر دي. @else لسه ما فيش صوتيات. @endif
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>العنوان</th><th>الموضوع</th><th class="hide-sm">المدة</th><th class="hide-sm">الملف</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                @foreach ($items as $a)
                    <tr>
                        <td class="title-cell">
                            <a href="{{ route('admin.audios.edit', $a) }}">{{ $a->title }}</a>
                            <small>
                                @if ($a->series) {{ $a->series->title }} #{{ $a->series_order }} · @endif
                                {{ $a->published_at?->translatedFormat('j F Y') ?? 'غير منشور' }}
                            </small>
                        </td>
                        <td>{{ $a->topic?->name ?? '—' }}</td>
                        <td class="num hide-sm">{{ \App\Support\Arabic::formatDuration($a->duration) }}</td>
                        <td class="hide-sm">
                            @if ($a->file_path) <span class="badge badge-published"><i class="ti ti-file-music" aria-hidden="true"></i> مرفوع</span>
                            @elseif ($a->external_url) <span class="badge badge-soft"><i class="ti ti-link" aria-hidden="true"></i> رابط</span>
                            @else <span class="badge badge-scheduled"><i class="ti ti-alert-triangle" aria-hidden="true"></i> بدون ملف</span> @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.toggle', ['audios', $a->id]) }}">@csrf
                                <button class="status-toggle" title="اضغط للتبديل"><x-status :item="$a" /></button>
                            </form>
                        </td>
                        <td class="row-actions">
                            <a class="icon-btn" href="{{ route('admin.audios.edit', $a) }}" title="تعديل" aria-label="تعديل"><i class="ti ti-edit"></i></a>
                            <x-delete-form :action="route('admin.audios.destroy', $a)" label="الصوتية «{{ $a->title }}»" />
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
