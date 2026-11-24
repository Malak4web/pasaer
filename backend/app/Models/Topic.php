<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'sort_order'];

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn ($q) => $q->orderBy('sort_order')->orderBy('id'));
    }

    public function videos(): HasMany { return $this->hasMany(Video::class); }
    public function audios(): HasMany { return $this->hasMany(Audio::class); }
    public function lessons(): HasMany { return $this->hasMany(Lesson::class); }
    public function posts(): HasMany { return $this->hasMany(Post::class); }
    public function series(): HasMany { return $this->hasMany(Series::class); }
}
