<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Series;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * أساس مشترك لشاشات الإدارة: قائمة بفلاتر وترقيم، إنشاء، تعديل، حذف.
 * كل شاشة تحدد الموديل والقواعد وطريقة تجهيز البيانات.
 */
abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** اسم المسار والمجلد: videos, audios ... */
    protected string $resource;

    /** "المقطع" — تُستخدم في رسائل النجاح */
    protected string $singular;

    protected array $with = ['topic'];

    protected array $filters = ['q', 'topic', 'status', 'series'];

    abstract protected function rules(Request $request, ?Model $item): array;

    /** تحويل المدخلات المتحقق منها إلى أعمدة (يمكن تخصيصه) */
    protected function prepare(array $data, Request $request, ?Model $item): array
    {
        return $data;
    }

    protected function afterSave(Model $item, Request $request): void {}

    protected function baseQuery(): Builder
    {
        return $this->model::query()->with($this->with);
    }

    protected function sortQuery(Builder $q): Builder
    {
        return $q->orderByRaw('published_at IS NULL DESC')->latest('published_at')->latest('id');
    }

    /** بيانات إضافية لنموذج الإنشاء/التعديل */
    protected function formData(): array
    {
        return [
            'topics' => Topic::all(),
            'seriesList' => Series::orderBy('title')->get(),
        ];
    }

    public function index(Request $request)
    {
        $query = $this->baseQuery()->filter($request->only($this->filters));
        $items = $this->sortQuery($query)->paginate(15)->withQueryString();

        return view("admin.{$this->resource}.index", [
            'items' => $items,
            'filters' => $request->only($this->filters),
        ] + $this->formData());
    }

    public function create()
    {
        return view("admin.{$this->resource}.form", ['item' => new $this->model] + $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->prepare($request->validate($this->rules($request, null), $this->messages()), $request, null);
        $item = $this->model::create($data);
        $this->afterSave($item, $request);

        return $this->redirectAfterSave($request, $item, "تمت إضافة {$this->singular}.");
    }

    public function edit(string $id)
    {
        return view("admin.{$this->resource}.form", ['item' => $this->model::findOrFail($id)] + $this->formData());
    }

    public function update(Request $request, string $id)
    {
        $item = $this->model::findOrFail($id);
        $data = $this->prepare($request->validate($this->rules($request, $item), $this->messages()), $request, $item);
        $item->update($data);
        $this->afterSave($item, $request);

        return $this->redirectAfterSave($request, $item, "تم حفظ {$this->singular}.");
    }

    public function destroy(string $id)
    {
        $this->model::findOrFail($id)->delete();

        return redirect()->route("admin.{$this->resource}.index")->with('success', "تم حذف {$this->singular}.");
    }

    protected function redirectAfterSave(Request $request, Model $item, string $message)
    {
        $route = $request->input('after') === 'new'
            ? route("admin.{$this->resource}.create")
            : route("admin.{$this->resource}.edit", $item);

        return redirect($route)->with('success', $message);
    }

    /** قواعد مشتركة لحقول النشر والتصنيف */
    protected function publishingRules(bool $inSeries = true): array
    {
        return [
            'topic_id' => ['nullable', 'exists:topics,id'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
        ] + ($inSeries ? [
            'series_id' => ['nullable', 'exists:series,id'],
            'series_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ] : []);
    }

    protected function messages(): array
    {
        return [];
    }
}
