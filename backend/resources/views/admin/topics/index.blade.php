@extends('admin.layout')
@section('title', 'المواضيع')
@section('crumb', 'المواضيع')

@section('content')
<div class="page-head">
    <div><h1>المواضيع</h1><p>التصنيفات اللي بتظهر في القائمة الجانبية والفلاتر.</p></div>
    <a href="{{ route('admin.topics.create') }}" class="btn btn-primary"><i class="ti ti-plus" aria-hidden="true"></i> موضوع جديد</a>
</div>

<div class="card">
    @if ($items->isEmpty())
        <div class="empty"><i class="ti ti-category" aria-hidden="true"></i> لسه ما فيش مواضيع.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>الموضوع</th><th>الرابط</th><th>المحتوى</th><th class="hide-sm">الترتيب</th><th></th></tr></thead>
                <tbody>
                @foreach ($items as $t)
                    <tr>
                        <td><span class="icon-preview"><i class="ti {{ $t->icon }}" aria-hidden="true"></i> <a href="{{ route('admin.topics.edit', $t) }}"><strong>{{ $t->name }}</strong></a></span></td>
                        <td class="muted" dir="ltr" style="text-align:start">/topics/{{ $t->slug }}</td>
                        <td class="num">{{ $t->videos_count }} مرئي · {{ $t->audios_count }} صوتي · {{ $t->lessons_count }} درس · {{ $t->posts_count }} منشور</td>
                        <td class="num hide-sm">{{ $t->sort_order }}</td>
                        <td class="row-actions">
                            <a class="icon-btn" href="{{ route('admin.topics.edit', $t) }}" title="تعديل" aria-label="تعديل"><i class="ti ti-edit"></i></a>
                            <x-delete-form :action="route('admin.topics.destroy', $t)" label="موضوع «{{ $t->name }}» (المحتوى هيفضل بدون موضوع)" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
