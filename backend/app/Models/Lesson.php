<?php

namespace App\Models;

use App\Models\Concerns\InSeries;
use App\Models\Concerns\IsContent;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use InSeries, IsContent;

    protected $fillable = ['title', 'excerpt', 'body', 'topic_id', 'series_id', 'series_order', 'status', 'published_at'];

    protected static function booted(): void
    {
        static::saving(fn (Lesson $m) => $m->read_minutes = Arabic::readMinutes($m->body));
    }

    public function searchableFields(): array
    {
        return ['title', 'excerpt', 'body'];
    }

    /** النص مقسّم إلى فقرات (سطر فارغ يفصل بين الفقرات) */
    public function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\R\s*\R/u", (string) $this->body))));
    }
}
