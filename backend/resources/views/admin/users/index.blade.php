@extends('admin.layout')
@section('title', 'المستخدمون')
@section('crumb', 'المستخدمون')

@section('content')
<div class="page-head">
    <div><h1>المستخدمون</h1><p>المدير يتحكم في كل حاجة، والمحرر يدير المحتوى بس.</p></div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="ti ti-user-plus" aria-hidden="true"></i> مستخدم جديد</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>الاسم</th><th>الصلاحية</th><th class="hide-sm">آخر دخول</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @foreach ($items as $u)
                <tr>
                    <td>
                        <span class="me">
                            <span class="avatar" aria-hidden="true">{{ mb_substr($u->name, 0, 1) }}</span>
                            <span class="me-info"><strong>{{ $u->name }} @if ($u->id === auth()->id())<span class="muted">(أنت)</span>@endif</strong><span dir="ltr" style="text-align:start">{{ $u->email }}</span></span>
                        </span>
                    </td>
                    <td><span class="badge {{ $u->isAdmin() ? 'badge-admin' : 'badge-draft' }}">{{ \App\Models\User::ROLES[$u->role] ?? $u->role }}</span></td>
                    <td class="num hide-sm">{{ $u->last_login_at?->diffForHumans() ?? 'لم يدخل بعد' }}</td>
                    <td>@if ($u->is_active)<span class="badge badge-published">نشط</span>@else<span class="badge badge-scheduled">موقوف</span>@endif</td>
                    <td class="row-actions">
                        <a class="icon-btn" href="{{ route('admin.users.edit', $u) }}" title="تعديل" aria-label="تعديل"><i class="ti ti-edit"></i></a>
                        @if ($u->id !== auth()->id())
                            <x-delete-form :action="route('admin.users.destroy', $u)" label="حساب {{ $u->name }}" />
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
