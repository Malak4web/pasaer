<?php

namespace App\Http\Controllers\Admin;

use App\Models\Series;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SeriesController extends CrudController
{
    protected string $model = Series::class;
    protected string $resource = 'series';
    protected string $singular = 'السلسلة';

    protected function baseQuery(): Builder
    {
        return Series::query()->with('topic')->withCount(['videos', 'audios', 'lessons']);
    }

    protected function sortQuery(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function index(Request $request)
    {
        $items = $this->baseQuery()
            ->when($request->q, fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->topic, fn ($q, $v) => $q->where('topic_id', $v))
            ->orderBy('sort_order')->orderBy('id')
            ->paginate(20)->withQueryString();

        return view('admin.series.index', ['items' => $items, 'filters' => $request->only('q', 'topic'), 'topics' => Topic::all()]);
    }

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'topic_id' => ['nullable', 'exists:topics,id'],
            'kind' => ['required', 'in:'.implode(',', array_keys(Series::KINDS))],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:draft,published'],
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item): array
    {
        $data['is_featured'] = $request->boolean('is_featured');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    protected function formData(): array
    {
        return ['topics' => Topic::all()];
    }

    public function edit(string $id)
    {
        $item = Series::findOrFail($id);

        // كل عناصر السلسلة (حتى المسودات) لعرضها في صفحة التعديل
        $members = collect()
            ->concat($item->videos()->get()->map(fn ($m) => ['resource' => 'videos', 'label' => 'مرئي', 'm' => $m]))
            ->concat($item->audios()->get()->map(fn ($m) => ['resource' => 'audios', 'label' => 'صوتي', 'm' => $m]))
            ->concat($item->lessons()->get()->map(fn ($m) => ['resource' => 'lessons', 'label' => 'درس', 'm' => $m]))
            ->sortBy([fn ($a, $b) => $a['m']->series_order <=> $b['m']->series_order]);

        return view('admin.series.form', ['item' => $item, 'members' => $members] + $this->formData());
    }
}
