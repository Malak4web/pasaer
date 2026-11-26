<?php

namespace App\Console\Commands;

use App\Models\Audio;
use App\Models\Series;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportTelegramAudios extends Command
{
    protected $signature = 'audios:import
        {file : مسار ملف JSON الناتج (يدعم الملف المصنّف أو الخام)}
        {--status=published : حالة النشر الافتراضية (draft أو published)}
        {--topic= : رقم الموضوع topic_id الافتراضي (اختياري)}
        {--series= : رقم السلسلة series_id الافتراضي (اختياري)}';

    protected $description = 'استيراد الصوتيات بروابط Archive.org المباشرة مع المواضيع والسلاسل بدون تكرار';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("الملف غير موجود: {$path}");

            return self::FAILURE;
        }

        $status = $this->option('status');
        if (! in_array($status, ['draft', 'published'], true)) {
            $this->error('قيمة --status لازم تكون draft أو published.');

            return self::FAILURE;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw)) {
            $this->error('تعذر قراءة ملف JSON.');

            return self::FAILURE;
        }

        // 1. إنشاء المواضيع إن وُجدت في الملف
        $topicMap = Topic::pluck('id', 'slug')->all();
        foreach ($raw['topics'] ?? [] as $t) {
            $slug = trim((string) ($t['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $topic = Topic::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => (string) ($t['name'] ?? $slug),
                    'icon' => (string) ($t['icon'] ?? 'ti-folder'),
                    'sort_order' => (int) ($t['sort_order'] ?? 0),
                ]
            );
            $topicMap[$slug] = $topic->id;
        }

        // 2. إنشاء السلاسل إن وُجدت في الملف
        $seriesMap = Series::where('kind', 'audio')->pluck('id', 'title')->all();
        foreach ($raw['series'] ?? [] as $s) {
            $title = trim((string) ($s['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $series = Series::firstOrCreate(
                ['title' => $title, 'kind' => (string) ($s['kind'] ?? 'audio')],
                [
                    'description' => (string) ($s['description'] ?? ''),
                    'topic_id' => $topicMap[$s['topic_slug'] ?? ''] ?? null,
                    'is_featured' => (bool) ($s['is_featured'] ?? false),
                    'sort_order' => (int) ($s['sort_order'] ?? 0),
                ]
            );
            $seriesMap[$title] = $series->id;
        }

        // 3. استخراج قائمة الصوتيات (يدعم الهيكل المصنّف { audios: [...] } أو الخام)
        if (isset($raw['audios']) && is_array($raw['audios'])) {
            $items = $raw['audios'];
        } else {
            $items = array_is_list($raw) ? $raw : array_values($raw);
        }

        $defaultTopicId = $this->option('topic') ? (int) $this->option('topic') : null;
        $defaultSeriesId = $this->option('series') ? (int) $this->option('series') : null;

        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $url = trim((string) ($item['external_url'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));

            if ($url === '' || $title === '') {
                $skipped++;

                continue;
            }

            // تجنّب التكرار وعدم المساس بأي تعديلات يدوية لاحقة من لوحة التحكم
            if (Audio::where('external_url', $url)->exists()) {
                $skipped++;

                continue;
            }

            $publishedAt = null;
            if (! empty($item['published_at'])) {
                try {
                    $publishedAt = Carbon::parse($item['published_at']);
                } catch (\Throwable) {
                    $publishedAt = now();
                }
            }

            $topicId = $topicMap[$item['topic_slug'] ?? ''] ?? $defaultTopicId;
            $seriesId = $seriesMap[$item['series_title'] ?? ''] ?? $defaultSeriesId;
            $seriesOrder = (int) ($item['series_order'] ?? 0);

            Audio::create([
                'title' => Str::limit($title, 250, ''),
                'external_url' => Str::limit($url, 500, ''),
                'description' => ! empty($item['description']) ? (string) $item['description'] : null,
                'duration' => max(0, (int) ($item['duration'] ?? 0)),
                'topic_id' => $topicId,
                'series_id' => $seriesId,
                'series_order' => $seriesOrder,
                'status' => $status,
                'published_at' => $status === 'published' ? ($publishedAt ?? now()) : $publishedAt,
            ]);

            $created++;
        }

        $this->info("تم الاستيراد بنجاح: أُضيف {$created} صوتية، وتُخطي {$skipped} (موجود سابقًا أو ناقص).");

        return self::SUCCESS;
    }
}
