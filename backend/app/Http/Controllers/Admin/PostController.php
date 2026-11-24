<?php

namespace App\Http\Controllers\Admin;

use App\Models\Post;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PostController extends CrudController
{
    protected string $model = Post::class;
    protected string $resource = 'posts';
    protected string $singular = 'المنشور';
    protected array $filters = ['q', 'topic', 'status'];

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
        ] + $this->publishingRules(inSeries: false);
    }

    protected function formData(): array
    {
        return ['topics' => Topic::all()];
    }
}
