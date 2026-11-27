<?php

namespace App\Console\Commands;

use App\Models\Series;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportYoutubeVideos extends Command
{
    protected $signature = 'videos:import
        {file : مسار ملف mahmoud_azhary_videos.json المصنّف}
        {--status=published : حالة النشر الافتراضية (draft أو published)}';

    protected $description = 'استيراد المرئيات من ملف JSON المصنّف بروابط يوتيوب مباشرة مع السلاسل والمواضيع بدون تكرار';

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

        if (! empty($raw['channel_url'])) {
            Setting::set('youtube_channel_url', (string) $raw['channel_url']);
        }

        // خريطة المواضيع الموجودة (وإنشاء أي موضوع ناقص)
        $topicMap = Topic::pluck('id', 'slug')->all();
        $defaultTopics = [
            'khutab' => ['خطب الجمعة', 'ti-microphone-2', 0],
            'quran' => ['تلاوات قرآنية', 'ti-book', 1],
            'tafsir' => ['التفسير', 'ti-book-2', 2],
            'seerah' => ['السيرة', 'ti-route', 3],
            'tazkiyah' => ['التزكية', 'ti-heart', 4],
            'fiqh' => ['الفقه', 'ti-scale', 5],
            'aqeedah' => ['العقيدة', 'ti-sun', 6],
            'family' => ['الأسرة', 'ti-home-heart', 7],
        ];
        foreach ($defaultTopics as $slug => [$name, $icon, $order]) {
            if (! isset($topicMap[$slug])) {
                $topicMap[$slug] = Topic::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'icon' => $icon, 'sort_order' => $order]
                )->id;
            }
        }

        // إنشاء السلاسل المرئية
        $seriesMap = Series::where('kind', 'video')->pluck('id', 'title')->all();
        foreach ($raw['series'] ?? [] as $s) {
            $title = trim((string) ($s['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $series = Series::firstOrCreate(
                ['title' => $title, 'kind' => 'video'],
                [
                    'description' => (string) ($s['description'] ?? ''),
                    'topic_id' => $topicMap[$s['topic_slug'] ?? ''] ?? null,
                    'is_featured' => (bool) ($s['is_featured'] ?? false),
                    'sort_order' => (int) ($s['sort_order'] ?? 0),
                ]
            );
            $seriesMap[$title] = $series->id;
        }

        $items = $raw['videos'] ?? [];
        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $ytId = trim((string) ($item['youtube_id'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));

            if ($ytId === '' || $title === '') {
                $skipped++;

                continue;
            }

            if (Video::where('youtube_id', $ytId)->exists()) {
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

            Video::create([
                'title' => Str::limit($title, 250, ''),
                'youtube_id' => Str::limit($ytId, 20, ''),
                'description' => ! empty($item['description']) ? (string) $item['description'] : null,
                'duration' => max(0, (int) ($item['duration'] ?? 0)),
                'views' => max(0, (int) ($item['views'] ?? 0)),
                'topic_id' => $topicMap[$item['topic_slug'] ?? ''] ?? null,
                'series_id' => $seriesMap[$item['series_title'] ?? ''] ?? null,
                'series_order' => (int) ($item['series_order'] ?? 0),
                'status' => $status,
                'published_at' => $status === 'published' ? ($publishedAt ?? now()) : $publishedAt,
            ]);

            $created++;
        }

        $this->info("تم استيراد المرئيات بنجاح: أُضيف {$created} فيديو، وتُخطي {$skipped}.");

        return self::SUCCESS;
    }
}
