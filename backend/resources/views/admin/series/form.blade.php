@extends('admin.layout')
@php $isNew = ! $item->exists; @endphp
@section('title', $isNew ? 'سلسلة جديدة' : $item->title)
@section('crumb')
    <a href="{{ route('admin.series.index') }}">السلاسل</a> <i class="ti ti-chevron-left" aria-hidden="true"></i> {{ $isNew ? 'جديدة' : 'تعديل' }}
@endsection

@section('content')
<div class="page-head"><div><h1>{{ $isNew ? 'سلسلة جديدة' : 'تعديل السلسلة' }}</h1></div></div>

<form method="POST" action="{{ $isNew ? route('admin.series.store') : route('admin.series.update', $item) }}" class="form-grid" data-dirty-check>
    @csrf
    @unless ($isNew) @method('PUT') @endunless

    <div class="form-main">
        <div class="card card-pad fields">
            <x-field label="اسم السلسلة" name="title" required>
                <input type="text" id="title" name="title" class="title-input" required maxlength="255" value="{{ old('title', $item->title) }}">
            </x-field>
            <x-field label="وصف" name="description">
                <textarea id="description" name="description" rows="3" maxlength="2000">{{ old('description', $item->description) }}</textarea>
            </x-field>
            <div class="row-2">
                <x-field label="النوع" name="kind" required hint="يحدد شكل غلاف السلسلة في الموقع">
                    <select id="kind" name="kind">
                        @foreach (\App\Models\Series::KINDS as $k => $label)
                            <option value="{{ $k }}" @selected(old('kind', $item->kind ?? 'video') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="الموضوع" name="topic_id">
                    <select id="topic_id" name="topic_id">
                        <option value="">— بدون —</option>
                        @foreach ($topics as $t)<option value="{{ $t->id }}" @selected(old('topic_id', $item->topic_id) == $t->id)>{{ $t->name }}</option>@endforeach
                    </select>
                </x-field>
            </div>
        </div>

        @unless ($isNew)
            <div class="card">
                <div class="card-head"><h2>عناصر السلسلة ({{ $members->count() }})</h2></div>
                @if ($members->isEmpty())
                    <p class="muted" style="padding:16px 20px;font-size:14px">لسه ما فيش عناصر. افتح أي مقطع أو صوتية أو درس واختار السلسلة دي من خانة «السلسلة».</p>
                @else
                    <ul class="rows">
                        @foreach ($members as $m)
                            <li>
                                <span class="ico" aria-hidden="true">{{ $m['m']->series_order }}</span>
                                <div class="grow">
                                    <a href="{{ route("admin.{$m['resource']}.edit", $m['m']) }}">{{ $m['m']->title }}</a>
                                    <span>{{ $m['label'] }}</span>
                                </div>
                                <x-status :item="$m['m']" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endunless
    </div>

    <div class="form-side">
        <div class="card">
            <div class="card-head"><h2>العرض</h2></div>
            <div class="card-pad fields">
                <div class="field">
                    <span class="label">الحالة</span>
                    <div class="seg">
                        <label><input type="radio" name="status" value="draft" @checked(old('status', $item->status ?? 'published') === 'draft')> مسودة</label>
                        <label><input type="radio" name="status" value="published" @checked(old('status', $item->status ?? 'published') === 'published')> منشورة</label>
                    </div>
                </div>
                <input type="hidden" name="is_featured" value="0">
                <label class="check"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured))> تظهر في «سلاسل مختارة» بالرئيسية</label>
                <x-field label="ترتيب الظهور" name="sort_order" hint="الأصغر يظهر الأول">
                    <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
                </x-field>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> {{ $isNew ? 'إضافة' : 'حفظ' }}</button>
            </div>
        </div>
    </div>
</form>

@unless ($isNew)
    <div style="max-width:320px"><x-delete-form :action="route('admin.series.destroy', $item)" label="السلسلة (العناصر نفسها مش هتتحذف)" :button="true" /></div>
@endunless
@endsection
