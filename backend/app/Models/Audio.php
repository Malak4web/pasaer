<?php

namespace App\Models;

use App\Models\Concerns\InSeries;
use App\Models\Concerns\IsContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Audio extends Model
{
    use InSeries, IsContent;

    protected $table = 'audios';

    protected $fillable = [
        'title', 'file_path', 'external_url', 'description', 'duration', 'plays',
        'topic_id', 'series_id', 'series_order', 'status', 'published_at',
    ];

    protected static function booted(): void
    {
        // حذف الملف من التخزين عند حذف الصوتية
        static::deleted(function (Audio $audio) {
            if ($audio->file_path) {
                Storage::disk('public')->delete($audio->file_path);
            }
        });
    }

    public function searchableFields(): array
    {
        return ['title', 'description'];
    }

    /** رابط التشغيل: الملف المرفوع أولًا ثم الرابط الخارجي */
    public function srcUrl(): ?string
    {
        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }

        return $this->external_url ?: null;
    }
}
