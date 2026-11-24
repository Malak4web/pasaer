@extends('admin.layout')
@php $isNew = ! $item->exists; $self = $item->id === auth()->id(); @endphp
@section('title', $isNew ? 'مستخدم جديد' : $item->name)
@section('crumb')
    <a href="{{ route('admin.users.index') }}">المستخدمون</a> <i class="ti ti-chevron-left" aria-hidden="true"></i> {{ $isNew ? 'جديد' : 'تعديل' }}
@endsection

@section('content')
<div class="page-head"><div><h1>{{ $isNew ? 'مستخدم جديد' : 'تعديل المستخدم' }}</h1></div></div>

<form method="POST" action="{{ $isNew ? route('admin.users.store') : route('admin.users.update', $item) }}" class="card" style="max-width:600px">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <div class="card-pad fields">
        <x-field label="الاسم" name="name" required>
            <input type="text" id="name" name="name" required maxlength="100" value="{{ old('name', $item->name) }}">
        </x-field>
        <x-field label="البريد الإلكتروني" name="email" required>
            <input type="email" id="email" name="email" class="ltr" required value="{{ old('email', $item->email) }}">
        </x-field>
        <x-field label="الصلاحية" name="role" required :hint="$self ? 'ما ينفعش تغيّر صلاحيتك بنفسك.' : null">
            <select id="role" name="role" @disabled($self)>
                @foreach (\App\Models\User::ROLES as $k => $label)
                    <option value="{{ $k }}" @selected(old('role', $item->role ?? 'editor') === $k)>{{ $label }} — {{ $k === 'admin' ? 'كل الصلاحيات' : 'المحتوى فقط' }}</option>
                @endforeach
            </select>
            @if ($self)<input type="hidden" name="role" value="admin">@endif
        </x-field>
        <div class="row-2">
            <x-field label="{{ $isNew ? 'كلمة المرور' : 'كلمة مرور جديدة' }}" name="password" :required="$isNew" :hint="$isNew ? '٨ أحرف على الأقل' : 'اتركها فاضية لو مش عايز تغيّرها'">
                <input type="password" id="password" name="password" class="ltr" autocomplete="new-password" @required($isNew)>
            </x-field>
            <x-field label="تأكيدها" name="password_confirmation">
                <input type="password" id="password_confirmation" name="password_confirmation" class="ltr" autocomplete="new-password">
            </x-field>
        </div>
        @unless ($self)
            <input type="hidden" name="is_active" value="0">
            <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))> الحساب نشط ويقدر يسجّل دخول</label>
        @else
            <input type="hidden" name="is_active" value="1">
        @endunless
    </div>
    <div class="form-actions" style="flex-direction:row">
        <button class="btn btn-primary" style="width:auto">{{ $isNew ? 'إضافة' : 'حفظ' }}</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost" style="width:auto">إلغاء</a>
    </div>
</form>
@endsection
