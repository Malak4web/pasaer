<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Series extends Model
{
    public const KINDS = ['video' => 'مرئية', 'audio' => 'صوتية', 'text' => 'مكتوبة'];

    protected $fillable = ['title', 'description', 'topic_id', 'kind', 'is_featured', 'sort_order', 'status'];

    protected $casts = ['is_featured' => 'boolean'];

    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
    public function videos(): HasMany { return $this->hasMany(Video::class)->orderBy('series_order')->orderBy('published_at'); }
    public function audios(): HasMany { return $this->hasMany(Audio::class)->orderBy('series_order')->orderBy('published_at'); }
    public function lessons(): HasMany { return $this->hasMany(Lesson::class)->orderBy('series_order')->orderBy('published_at'); }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /** كل عناصر السلسلة المنشورة من كل الأنواع، مرتبة */
    public function publishedItems(): Collection
    {
        return collect()
            ->concat($this->videos()->published()->with('topic')->get())
            ->concat($this->audios()->published()->with('topic')->get())
            ->concat($this->lessons()->published()->with('topic')->get())
            ->sortBy([['series_order', 'asc'], ['published_at', 'asc']])
            ->values();
    }

    public function itemsCount(): int
    {
        return $this->videos()->published()->count()
            + $this->audios()->published()->count()
            + $this->lessons()->published()->count();
    }
}
