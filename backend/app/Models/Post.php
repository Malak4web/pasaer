<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use IsContent;

    protected $fillable = ['title', 'excerpt', 'body', 'topic_id', 'status', 'published_at'];

    protected static function booted(): void
    {
        static::saving(fn (Post $m) => $m->read_minutes = Arabic::readMinutes($m->body));
    }

    public function searchableFields(): array
    {
        return ['title', 'excerpt', 'body'];
    }

    public function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\R\s*\R/u", (string) $this->body))));
    }
}
