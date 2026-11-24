<?php

namespace App\Models\Concerns;

use App\Models\Topic;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سلوك مشترك لكل أنواع المحتوى: النشر، التصنيف، البحث.
 * كل موديل يعرّف searchableFields() ليحدد الحقول الداخلة في فهرس البحث.
 */
trait IsContent
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public static function bootIsContent(): void
    {
        static::saving(function ($model) {
            $text = collect($model->searchableFields())->map(fn ($f) => $model->{$f})->implode(' ');
            $model->search_index = Arabic::normalize($text.' '.$model->topic?->name);

            if ($model->status === self::STATUS_PUBLISHED && ! $model->published_at) {
                $model->published_at = now();
            }
        });
    }

    public function initializeIsContent(): void
    {
        $this->mergeCasts(['published_at' => 'datetime']);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /** المنشور فعليًا (يستبعد المجدول لموعد لاحق) */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = Arabic::normalize($term);

        return $term === '' ? $query : $query->where('search_index', 'like', '%'.addcslashes($term, '%_\\').'%');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->search($v))
            ->when($filters['topic'] ?? null, fn ($q, $v) => $q->where('topic_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['series'] ?? null, fn ($q, $v) => $q->where('series_id', $v));
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at && $this->published_at->isPast();
    }
}
