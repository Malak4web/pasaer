{{-- قائمة مشتركة للدروس والمنشورات: $resource, $title, $subtitle, $addLabel, $items, $filters, $topics, $seriesList? --}}
@extends('admin.layout')
@section('title', $title)
@section('crumb', $title)

@section('content')
<div class="page-head">
    <div><h1>{{ $title }}</h1><p>{{ $subtitle }}</p></div>
    <a href="{{ route("admin.{$resource}.create") }}" class="btn btn-primary"><i class="ti ti-plus" aria-hidden="true"></i> {{ $addLabel }}</a>
</div>

<div class="card">
    <x-filters :filters="$filters" :topics="$topics" :series-list="$seriesList ?? null" />
    @if ($items->isEmpty())
        <div class="empty"><i class="ti ti-file-off" aria-hidden="true"></i>
            @if (array_filter($filters)) ما فيش نتايج للفلاتر دي. @else لسه ما فيش محتوى هنا. @endif
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>العنوان</th><th>الموضوع</th><th class="hide-sm">القراءة</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                @foreach ($items as $it)
                    <tr>
                        <td class="title-cell">
                            <a href="{{ route("admin.{$resource}.edit", $it) }}">{{ $it->title }}</a>
                            <small>
                                @if (isset($it->series_id) && $it->series) {{ $it->series->title }} #{{ $it->series_order }} · @endif
                                {{ $it->published_at?->translatedFormat('j F Y') ?? 'غير منشور' }}
                            </small>
                        </td>
                        <td>{{ $it->topic?->name ?? '—' }}</td>
                        <td class="num hide-sm">{{ $it->read_minutes }} د</td>
                        <td>
                            <form method="POST" action="{{ route('admin.toggle', [$resource, $it->id]) }}">@csrf
                                <button class="status-toggle" title="اضغط للتبديل"><x-status :item="$it" /></button>
                            </form>
                        </td>
                        <td class="row-actions">
                            <a class="icon-btn" href="{{ route("admin.{$resource}.edit", $it) }}" title="تعديل" aria-label="تعديل"><i class="ti ti-edit"></i></a>
                            <x-delete-form :action="route('admin.'.$resource.'.destroy', $it)" label="«{{ $it->title }}»" />
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
