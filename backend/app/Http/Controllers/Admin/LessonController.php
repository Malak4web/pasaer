<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LessonController extends CrudController
{
    protected string $model = Lesson::class;
    protected string $resource = 'lessons';
    protected string $singular = 'الدرس';
    protected array $with = ['topic', 'series'];

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
        ] + $this->publishingRules();
    }

    protected function prepare(array $data, Request $request, ?Model $item): array
    {
        $data['series_order'] = (int) ($data['series_order'] ?? 0);

        return $data;
    }
}
