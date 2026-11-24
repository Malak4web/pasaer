@extends('admin.layout')
@php $isNew = ! $item->exists; @endphp
@section('title', $isNew ? 'موضوع جديد' : $item->name)
@section('crumb')
    <a href="{{ route('admin.topics.index') }}">المواضيع</a> <i class="ti ti-chevron-left" aria-hidden="true"></i> {{ $isNew ? 'جديد' : 'تعديل' }}
@endsection

@section('content')
<div class="page-head"><div><h1>{{ $isNew ? 'موضوع جديد' : 'تعديل الموضوع' }}</h1></div></div>

<form method="POST" action="{{ $isNew ? route('admin.topics.store') : route('admin.topics.update', $item) }}" class="card" style="max-width:600px" data-dirty-check>
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <div class="card-pad fields">
        <x-field label="الاسم" name="name" required>
            <input type="text" id="name" name="name" required maxlength="100" value="{{ old('name', $item->name) }}" placeholder="العقيدة">
        </x-field>
        <x-field label="الرابط (بالإنجليزي)" name="slug" required hint="بيظهر في رابط صفحة الموضوع: /topics/aqeedah">
            <input type="text" id="slug" name="slug" class="ltr" required maxlength="100" value="{{ old('slug', $item->slug) }}" placeholder="aqeedah">
        </x-field>
        <div class="row-2">
            <x-field label="الأيقونة" name="icon">
                <div class="icon-preview">
                    <i class="ti {{ old('icon', $item->icon ?? 'ti-bookmark') }}" id="icon-preview" aria-hidden="true"></i>
                    <input type="text" id="icon" name="icon" class="ltr" value="{{ old('icon', $item->icon ?? 'ti-bookmark') }}" placeholder="ti-heart">
                </div>
                <span class="hint">من <a href="https://tabler.io/icons" target="_blank" rel="noopener" style="color:var(--primary)">tabler.io/icons</a> — اكتب الاسم مسبوقًا بـ ti-</span>
            </x-field>
            <x-field label="الترتيب" name="sort_order">
                <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
            </x-field>
        </div>
    </div>
    <div class="form-actions" style="flex-direction:row">
        <button class="btn btn-primary" style="width:auto">{{ $isNew ? 'إضافة' : 'حفظ' }}</button>
        <a href="{{ route('admin.topics.index') }}" class="btn btn-ghost" style="width:auto">إلغاء</a>
    </div>
</form>
@endsection
