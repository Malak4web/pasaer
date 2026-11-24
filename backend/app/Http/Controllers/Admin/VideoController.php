<?php

namespace App\Http\Controllers\Admin;

use App\Models\Video;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class VideoController extends CrudController
{
    protected string $model = Video::class;
    protected string $resource = 'videos';
    protected string $singular = 'المقطع';
    protected array $with = ['topic', 'series'];

    protected function rules(Request $request, ?Model $item): array
    {
        // نستخرج المعرّف من الرابط قبل التحقق من التكرار
        $request->merge(['youtube_id' => Arabic::youtubeId($request->input('youtube_url'))]);

        return [
            'youtube_url' => ['required', 'string', 'max:500'],
            'youtube_id' => ['required', Rule::unique('videos', 'youtube_id')->ignore($item?->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration' => ['nullable', 'regex:/^\d{1,2}(:\d{1,2}){0,2}$/'],
            'views' => ['nullable', 'integer', 'min:0'],
        ] + $this->publishingRules();
    }

    protected function messages(): array
    {
        return [
            'youtube_id.required' => 'الرابط ده مش رابط يوتيوب صحيح.',
            'youtube_id.unique' => 'المقطع ده مضاف قبل كده.',
            'duration.regex' => 'اكتب المدة بالشكل 28:14 أو 1:02:05.',
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item): array
    {
        unset($data['youtube_url']);
        $data['duration'] = Arabic::parseDuration($data['duration'] ?? '');
        $data['views'] = (int) ($data['views'] ?? 0);
        $data['series_order'] = (int) ($data['series_order'] ?? 0);

        // لو العنوان فاضي نجيبه من يوتيوب (oEmbed، من غير API key)
        if (empty($data['title'])) {
            $data['title'] = $this->fetchTitle($data['youtube_id']) ?? 'مقطع بدون عنوان';
        }

        return $data;
    }

    private function fetchTitle(string $id): ?string
    {
        try {
            $res = Http::timeout(6)->get('https://www.youtube.com/oembed', [
                'url' => "https://www.youtube.com/watch?v={$id}",
                'format' => 'json',
            ]);

            return $res->ok() ? $res->json('title') : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
