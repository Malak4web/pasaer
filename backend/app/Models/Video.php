<?php

namespace App\Models;

use App\Models\Concerns\InSeries;
use App\Models\Concerns\IsContent;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use InSeries, IsContent;

    protected $fillable = [
        'title', 'youtube_id', 'description', 'duration', 'views',
        'topic_id', 'series_id', 'series_order', 'status', 'published_at',
    ];

    public function searchableFields(): array
    {
        return ['title', 'description'];
    }

    public function thumbnailUrl(): string
    {
        return "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg";
    }
}
