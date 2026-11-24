<?php

namespace App\Support;

use App\Models\Audio;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\Video;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تجميع كل أنواع المحتوى في قائمة واحدة مرتبة بالتاريخ، مع ترقيم صفحات.
 * بنعمل UNION على (id, type, published_at) بس، وبعدين نحمّل الموديلات المطلوبة.
 */
class Feed
{
    public const MODELS = [
        'video' => Video::class,
        'audio' => Audio::class,
        'lesson' => Lesson::class,
        'post' => Post::class,
    ];

    /**
     * @param  array{topic_id?: int|null, types?: array, q?: string|null}  $filters
     */
    public static function paginate(array $filters = [], int $perPage = 12, int $page = 1): LengthAwarePaginator
    {
        $types = array_intersect($filters['types'] ?? array_keys(self::MODELS), array_keys(self::MODELS));

        $union = null;
        foreach ($types as $type) {
            $q = self::MODELS[$type]::query()->published()
                ->selectRaw('id, ? as type, published_at', [$type])
                ->when($filters['topic_id'] ?? null, fn ($q, $v) => $q->where('topic_id', $v))
                ->when($filters['q'] ?? null, fn ($q, $v) => $q->search($v));
            $union = $union ? $union->unionAll($q) : $q;
        }

        if (! $union) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $base = DB::query()->fromSub($union, 'feed');
        $total = (clone $base)->count();
        $rows = $base->orderByDesc('published_at')->orderByDesc('id')
            ->forPage($page, $perPage)->get();

        return new LengthAwarePaginator(self::hydrate($rows), $total, $perPage, $page);
    }

    /** تحويل صفوف (id, type) لموديلات بنفس الترتيب */
    public static function hydrate(Collection $rows): Collection
    {
        $loaded = $rows->groupBy('type')->map(function ($group, $type) {
            $model = self::MODELS[$type];
            $with = $type === 'post' ? ['topic'] : ['topic', 'series'];

            return $model::with($with)->whereIn('id', $group->pluck('id'))->get()->keyBy('id');
        });

        return $rows->map(fn ($r) => $loaded[$r->type][$r->id] ?? null)->filter()->values();
    }
}
