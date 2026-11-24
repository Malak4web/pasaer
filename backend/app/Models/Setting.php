<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** الإعدادات الافتراضية وتسمياتها في لوحة التحكم */
    public const DEFAULTS = [
        'site_name' => ['label' => 'اسم الموقع', 'value' => 'بصائر'],
        'tagline' => ['label' => 'الوصف المختصر', 'value' => 'مرئيات وصوتيات ودروس ومنشورات دعوية في مكان واحد'],
        'speaker' => ['label' => 'اسم الشيخ / المحاضر', 'value' => 'اسم الشيخ'],
        'youtube_channel_url' => ['label' => 'رابط قناة يوتيوب', 'value' => 'https://www.youtube.com/@channel'],
        'verse_text' => ['label' => 'الآية في القائمة الجانبية', 'value' => 'قَدْ جَاءَكُم بَصَائِرُ مِن رَّبِّكُمْ'],
        'verse_ref' => ['label' => 'مرجع الآية', 'value' => 'الأنعام: ١٠٤'],
        'featured_video_id' => ['label' => 'المقطع المميّز في الرئيسية', 'value' => ''],
    ];

    public static function allValues(): array
    {
        return Cache::rememberForever('settings', function () {
            $stored = static::query()->pluck('value', 'key')->all();

            return collect(self::DEFAULTS)->map(fn ($d, $k) => $stored[$k] ?? $d['value'])->all();
        });
    }

    public static function get(string $key, $default = null)
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                static::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }
        Cache::forget('settings');
    }
}
