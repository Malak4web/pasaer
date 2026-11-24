@extends('admin.layout')
@php $isNew = ! $item->exists; @endphp
@section('title', $isNew ? 'مقطع جديد' : $item->title)
@section('crumb')
    <a href="{{ route('admin.videos.index') }}">المرئيات</a> <i class="ti ti-chevron-left" aria-hidden="true"></i> {{ $isNew ? 'جديد' : 'تعديل' }}
@endsection

@section('content')
<div class="page-head">
    <div><h1>{{ $isNew ? 'إضافة مقطع' : 'تعديل المقطع' }}</h1>
        <p>الصق رابط المقطع من يوتيوب، ولو سبت العنوان فاضي هيتجاب تلقائي.</p></div>
</div>

<form method="POST" action="{{ $isNew ? route('admin.videos.store') : route('admin.videos.update', $item) }}" class="form-grid" data-dirty-check>
    @csrf
    @unless ($isNew) @method('PUT') @endunless

    <div class="form-main">
        <div class="card card-pad fields">
            <x-field label="رابط يوتيوب" name="youtube_id" for="youtube_url" required hint="أي صيغة: youtube.com/watch?v=… أو youtu.be/… أو shorts">
                <input type="url" id="youtube_url" name="youtube_url" class="ltr" required
                       value="{{ old('youtube_url', $item->youtube_id ? 'https://www.youtube.com/watch?v='.$item->youtube_id : '') }}"
                       placeholder="https://www.youtube.com/watch?v=…">
            </x-field>
            <div class="yt-preview" id="yt-preview"><img alt=""><span></span></div>

            <x-field label="العنوان" name="title" hint="اختياري — لو فاضي هنجيب عنوان المقطع من يوتيوب.">
                <input type="text" id="title" name="title" class="title-input" maxlength="255" value="{{ old('title', $item->title) }}">
            </x-field>

            <x-field label="وصف" name="description">
                <textarea id="description" name="description" rows="5" maxlength="5000">{{ old('description', $item->description) }}</textarea>
            </x-field>

            <div class="row-2">
                <x-field label="المدة" name="duration" hint="مثال: 28:14">
                    <input type="text" id="duration" name="duration" class="ltr" inputmode="numeric" placeholder="0:00"
                           value="{{ old('duration', $item->duration ? \App\Support\Arabic::formatDuration($item->duration) : '') }}">
                </x-field>
                <x-field label="عدد المشاهدات" name="views" hint="يظهر في الموقع">
                    <input type="number" id="views" name="views" min="0" value="{{ old('views', $item->views ?? 0) }}">
                </x-field>
            </div>
        </div>
    </div>

    <div class="form-side">
        <x-publish-box :item="$item" resource="videos" :topics="$topics" :series-list="$seriesList" />
    </div>
</form>

@unless ($isNew)
    <div style="max-width:320px"><x-delete-form :action="route('admin.videos.destroy', $item)" label="هذا المقطع" :button="true" /></div>
@endunless
@endsection
