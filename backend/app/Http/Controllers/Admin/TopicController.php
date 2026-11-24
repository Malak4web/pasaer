<?php

namespace App\Http\Controllers\Admin;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TopicController extends CrudController
{
    protected string $model = Topic::class;
    protected string $resource = 'topics';
    protected string $singular = 'الموضوع';

    public function index(Request $request)
    {
        $items = Topic::withCount(['videos', 'audios', 'lessons', 'posts', 'series'])->get();

        return view('admin.topics.index', compact('items'));
    }

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('topics')->ignore($item?->id)],
            'icon' => ['nullable', 'regex:/^ti-[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'slug.alpha_dash' => 'الرابط بالإنجليزي فقط: حروف وأرقام وشرطة (مثال: aqeedah).',
            'icon.regex' => 'اسم الأيقونة يبدأ بـ ti- (مثال: ti-heart).',
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item): array
    {
        $data['icon'] = $data['icon'] ?: 'ti-bookmark';
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    protected function formData(): array
    {
        return [];
    }

    protected function redirectAfterSave(Request $request, Model $item, string $message)
    {
        return redirect()->route('admin.topics.index')->with('success', $message);
    }
}
