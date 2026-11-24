{{-- نموذج مشترك للدروس والمنشورات: $resource, $section, $singular, $item, $topics, $seriesList? --}}
@extends('admin.layout')
@php $isNew = ! $item->exists; @endphp
@section('title', $isNew ? "{$singular} جديد" : $item->title)
@section('crumb')
    <a href="{{ route("admin.{$resource}.index") }}">{{ $section }}</a> <i class="ti ti-chevron-left" aria-hidden="true"></i> {{ $isNew ? 'جديد' : 'تعديل' }}
@endsection

@section('content')
<div class="page-head">
    <div><h1>{{ $isNew ? "إضافة {$singular}" : "تعديل {$singular}" }}</h1>
        <p>افصل بين الفقرات بسطر فاضي. زمن القراءة بيتحسب تلقائي.</p></div>
</div>

<form method="POST" action="{{ $isNew ? route("admin.{$resource}.store") : route("admin.{$resource}.update", $item) }}" class="form-grid" data-dirty-check>
    @csrf
    @unless ($isNew) @method('PUT') @endunless

    <div class="form-main">
        <div class="card card-pad fields">
            <x-field label="العنوان" name="title" required>
                <input type="text" id="title" name="title" class="title-input" maxlength="255" required value="{{ old('title', $item->title) }}">
            </x-field>

            <x-field label="مقتطف" name="excerpt" hint="سطر أو اتنين بيظهروا في القوائم.">
                <textarea id="excerpt" name="excerpt" rows="2" maxlength="500" data-count="excerpt-count">{{ old('excerpt', $item->excerpt) }}</textarea>
                <span class="hint" id="excerpt-count" style="align-self:flex-end"></span>
            </x-field>

            <x-field label="النص" name="body" required>
                <textarea id="body" name="body" class="body" required>{{ old('body', $item->body) }}</textarea>
            </x-field>
        </div>
    </div>

    <div class="form-side">
        <x-publish-box :item="$item" :resource="$resource" :topics="$topics" :series-list="$seriesList ?? null" />
    </div>
</form>

@unless ($isNew)
    <div style="max-width:320px"><x-delete-form :action="route('admin.'.$resource.'.destroy', $item)" label="«{{ $item->title }}»" :button="true" /></div>
@endunless
@endsection
