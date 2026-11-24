@extends('admin.layout')
@section('title', 'إعدادات الموقع')
@section('crumb', 'إعدادات الموقع')

@section('content')
<div class="page-head"><div><h1>إعدادات الموقع</h1><p>بيانات عامة بتظهر في الواجهة.</p></div></div>

<form method="POST" action="{{ route('admin.settings.update') }}" class="card" style="max-width:720px" data-dirty-check>
    @csrf @method('PUT')
    <div class="card-pad fields">
        <div class="row-2">
            <x-field :label="$fields['site_name']['label']" name="site_name" required>
                <input type="text" id="site_name" name="site_name" required value="{{ old('site_name', $values['site_name']) }}">
            </x-field>
            <x-field :label="$fields['speaker']['label']" name="speaker">
                <input type="text" id="speaker" name="speaker" value="{{ old('speaker', $values['speaker']) }}">
            </x-field>
        </div>
        <x-field :label="$fields['tagline']['label']" name="tagline">
            <input type="text" id="tagline" name="tagline" maxlength="255" value="{{ old('tagline', $values['tagline']) }}">
        </x-field>
        <x-field :label="$fields['youtube_channel_url']['label']" name="youtube_channel_url">
            <input type="url" id="youtube_channel_url" name="youtube_channel_url" class="ltr" value="{{ old('youtube_channel_url', $values['youtube_channel_url']) }}">
        </x-field>
        <div class="row-2">
            <x-field :label="$fields['verse_text']['label']" name="verse_text">
                <input type="text" id="verse_text" name="verse_text" value="{{ old('verse_text', $values['verse_text']) }}" style="font-family:'Amiri',serif;font-size:17px">
            </x-field>
            <x-field :label="$fields['verse_ref']['label']" name="verse_ref">
                <input type="text" id="verse_ref" name="verse_ref" value="{{ old('verse_ref', $values['verse_ref']) }}">
            </x-field>
        </div>
        <x-field :label="$fields['featured_video_id']['label']" name="featured_video_id" hint="لو ما اخترتش، هيظهر أحدث مقطع منشور.">
            <select id="featured_video_id" name="featured_video_id">
                <option value="">— أحدث مقطع تلقائيًا —</option>
                @foreach ($videos as $v)
                    <option value="{{ $v->id }}" @selected(old('featured_video_id', $values['featured_video_id']) == $v->id)>{{ $v->title }}</option>
                @endforeach
            </select>
        </x-field>
    </div>
    <div class="form-actions" style="flex-direction:row">
        <button class="btn btn-primary" style="width:auto"><i class="ti ti-device-floppy" aria-hidden="true"></i> حفظ الإعدادات</button>
    </div>
</form>
@endsection
