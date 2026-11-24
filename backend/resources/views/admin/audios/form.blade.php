@extends('admin.layout')
@php $isNew = ! $item->exists; @endphp
@section('title', $isNew ? 'صوتية جديدة' : $item->title)
@section('crumb')
    <a href="{{ route('admin.audios.index') }}">الصوتيات</a> <i class="ti ti-chevron-left" aria-hidden="true"></i> {{ $isNew ? 'جديدة' : 'تعديل' }}
@endsection

@section('content')
<div class="page-head">
    <div><h1>{{ $isNew ? 'رفع صوتية' : 'تعديل الصوتية' }}</h1>
        <p>ارفع ملف صوت أو حط رابط مباشر له. المدة بتتحسب تلقائي.</p></div>
</div>

<form method="POST" action="{{ $isNew ? route('admin.audios.store') : route('admin.audios.update', $item) }}" enctype="multipart/form-data" class="form-grid" data-dirty-check>
    @csrf
    @unless ($isNew) @method('PUT') @endunless

    <div class="form-main">
        <div class="card card-pad fields">
            <x-field name="file">
                @if ($item->file_path)
                    <div class="file-current">
                        <i class="ti ti-file-music" aria-hidden="true"></i>
                        <audio controls preload="none" src="{{ $item->srcUrl() }}"></audio>
                        <label class="check"><input type="checkbox" name="remove_file" value="1"> حذف الملف</label>
                    </div>
                @endif
                <label class="dropzone">
                    <i class="ti ti-cloud-upload" aria-hidden="true"></i>
                    <strong data-file-label>{{ $item->file_path ? 'اسحب ملف جديد هنا لاستبدال الحالي' : 'اسحب ملف الصوت هنا أو اضغط للاختيار' }}</strong>
                    <span class="hint">mp3, m4a, ogg, wav — حتى ٢٠٠ ميجا</span>
                    <input type="file" name="file" accept="audio/*">
                </label>
            </x-field>

            <x-field label="أو رابط مباشر للملف" name="external_url" hint="مثلًا من أرشيف أو تخزين سحابي. الملف المرفوع له الأولوية.">
                <input type="url" id="external_url" name="external_url" class="ltr" value="{{ old('external_url', $item->external_url) }}" placeholder="https://…/lecture.mp3">
            </x-field>

            <x-field label="العنوان" name="title" required>
                <input type="text" id="title" name="title" class="title-input" maxlength="255" required value="{{ old('title', $item->title) }}">
            </x-field>

            <x-field label="وصف" name="description">
                <textarea id="description" name="description" rows="4" maxlength="5000">{{ old('description', $item->description) }}</textarea>
            </x-field>

            <x-field label="المدة" name="duration" hint="بتتملى تلقائي لما تختار الملف" style="max-width:200px">
                <input type="text" id="duration" name="duration" class="ltr" placeholder="0:00"
                       value="{{ old('duration', $item->duration ? \App\Support\Arabic::formatDuration($item->duration) : '') }}">
            </x-field>
        </div>
    </div>

    <div class="form-side">
        <x-publish-box :item="$item" resource="audios" :topics="$topics" :series-list="$seriesList" />
    </div>
</form>

@unless ($isNew)
    <div style="max-width:320px"><x-delete-form :action="route('admin.audios.destroy', $item)" label="هذه الصوتية وملفها" :button="true" /></div>
@endunless
@endsection
