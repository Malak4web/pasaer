@extends('admin.layout')
@section('title', 'حسابي')
@section('crumb', 'حسابي')

@section('content')
<div class="page-head">
    <div><h1>حسابي</h1><p>بياناتك وكلمة المرور.</p></div>
</div>

<form method="POST" action="{{ route('admin.account.update') }}" class="card" style="max-width:560px">
    @csrf @method('PUT')
    <div class="card-pad fields">
        <x-field label="الاسم" name="name" required>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
        </x-field>
        <x-field label="البريد الإلكتروني" name="email" required>
            <input type="email" id="email" name="email" class="ltr" value="{{ old('email', $user->email) }}" required>
        </x-field>
        <hr style="border:0;border-top:1px solid var(--border);margin:4px 0">
        <span class="label">تغيير كلمة المرور <span class="hint">(اختياري)</span></span>
        <x-field label="كلمة المرور الحالية" name="current_password">
            <input type="password" id="current_password" name="current_password" class="ltr" autocomplete="current-password">
        </x-field>
        <div class="row-2">
            <x-field label="كلمة المرور الجديدة" name="password" hint="٨ أحرف على الأقل">
                <input type="password" id="password" name="password" class="ltr" autocomplete="new-password">
            </x-field>
            <x-field label="تأكيدها" name="password_confirmation">
                <input type="password" id="password_confirmation" name="password_confirmation" class="ltr" autocomplete="new-password">
            </x-field>
        </div>
    </div>
    <div class="form-actions" style="flex-direction:row;justify-content:flex-start">
        <button class="btn btn-primary" style="width:auto">حفظ</button>
    </div>
</form>
@endsection
