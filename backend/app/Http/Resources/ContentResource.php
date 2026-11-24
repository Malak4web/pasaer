<?php

namespace App\Http\Resources;

use App\Models\Audio;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * شكل موحّد لكل أنواع المحتوى في الـ API.
 * ‎?detail=true (أو withDetail()) يضيف النص الكامل والوصف.
 */
class ContentResource extends JsonResource
{
    private bool $detail = false;

    public function withDetail(): static
    {
        $this->detail = true;

        return $this;
    }

    public static function typeOf($model): string
    {
        return match (true) {
            $model instanceof Video => 'video',
            $model instanceof Audio => 'audio',
            $model instanceof Lesson => 'lesson',
            $model instanceof Post => 'post',
        };
    }

    public function toArray(Request $request): array
    {
        $m = $this->resource;
        $type = self::typeOf($m);

        $base = [
            'id' => $m->id,
            'type' => $type,
            'key' => "{$type}-{$m->id}",
            'title' => $m->title,
            'date' => $m->published_at?->toIso8601String(),
            'topic' => $m->relationLoaded('topic') && $m->topic
                ? ['slug' => $m->topic->slug, 'name' => $m->topic->name, 'icon' => $m->topic->icon]
                : null,
            'series' => $type !== 'post' && $m->relationLoaded('series') && $m->series
                ? ['id' => $m->series->id, 'title' => $m->series->title]
                : null,
            'seriesOrder' => $type !== 'post' ? $m->series_order : null,
        ];

        $extra = match ($type) {
            'video' => [
                'youtubeId' => $m->youtube_id,
                'duration' => $m->duration,
                'views' => $m->views,
            ],
            'audio' => [
                'src' => $m->srcUrl(),
                'duration' => $m->duration,
            ],
            'lesson', 'post' => [
                'excerpt' => $m->excerpt,
                'readMinutes' => $m->read_minutes,
            ],
        };

        if ($this->detail) {
            $extra += match ($type) {
                'video', 'audio' => ['description' => $m->description],
                'lesson', 'post' => ['body' => $m->paragraphs()],
            };
        }

        return $base + $extra;
    }
}
