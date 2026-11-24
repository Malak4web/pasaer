<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends CrudController
{
    protected string $model = User::class;
    protected string $resource = 'users';
    protected string $singular = 'المستخدم';

    public function index(Request $request)
    {
        return view('admin.users.index', ['items' => User::orderBy('name')->get()]);
    }

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($item?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$item ? 'nullable' : 'required', 'min:8', 'confirmed'],
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item): array
    {
        $data['is_active'] = $request->boolean('is_active');

        // ما ينفعش تقفل نفسك برا أو تشيل صلاحية المدير عن نفسك
        if ($item && $item->id === $request->user()->id) {
            $data['is_active'] = true;
            $data['role'] = 'admin';
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    protected function formData(): array
    {
        return [];
    }

    public function destroy(string $id)
    {
        abort_if((int) $id === request()->user()->id, 422, 'لا يمكنك حذف حسابك.');

        return parent::destroy($id);
    }

    protected function redirectAfterSave(Request $request, Model $item, string $message)
    {
        return redirect()->route('admin.users.index')->with('success', $message);
    }
}
