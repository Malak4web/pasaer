<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentResource;
use App\Http\Resources\SeriesResource;
use App\Models\Audio;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\Series;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\Video;
use App\Support\Feed;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * الـ API العام للواجهة (قراءة فقط، بدون مصادقة).
 */
class PublicController extends Controller
{
    /** بيانات تحتاجها الواجهة عند التحميل: الإعدادات والمواضيع */
    public function site()
    {
        $s = Setting::allValues();

        return [
            'name' => $s['site_name'],
            'tagline' => $s['tagline'],
            'speaker' => $s['speaker'],
            'youtubeChannelUrl' => $s['youtube_channel_url'],
            'verse' => ['text' => $s['verse_text'], 'ref' => $s['verse_ref']],
            'topics' => Topic::all()->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name, 'icon' => $t->icon]),
        ];
    }

    public function home(Request $request)
    {
        $featuredId = Setting::get('featured_video_id');
        $featured = ($featuredId ? Video::published()->with('topic', 'series')->find($featuredId) : null)
            ?? Video::published()->with('topic', 'series')->latest('published_at')->first();

        return [
            'featured' => $featured ? new ContentResource($featured) : null,
            'latestAudio' => ($a = Audio::published()->with('topic', 'series')->latest('published_at')->first()) ? new ContentResource($a) : null,
            'series' => SeriesResource::collection($this->featuredSeries()),
        ];
    }

    /** قائمة مختلطة من كل الأنواع: ?topic=slug&type=video,audio&q=&page= */
    public function feed(Request $request)
    {
        $feed = Feed::paginate([
            'topic_id' => $this->topicId($request),
            'types' => $request->filled('type') ? explode(',', $request->type) : null,
            'q' => $request->q,
        ], min((int) $request->input('per_page', 12), 50), max(1, (int) $request->page));

        return $this->paginated($feed);
    }

    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $page = Feed::paginate(['q' => $request->q], 50);

        // عدد النتائج لكل نوع للتبويبات
        $counts = collect(Feed::MODELS)->map(fn ($m) => $m::published()->search($request->q)->count())->filter();

        return $this->paginated($page) + ['counts' => $counts];
    }

    // ---------- المرئيات ----------

    public function videos(Request $request)
    {
        $q = Video::published()->with('topic', 'series')
            ->when($this->topicId($request), fn ($q, $v) => $q->where('topic_id', $v));
        $q = $request->sort === 'popular' ? $q->orderByDesc('views') : $q->latest('published_at');

        return $this->paginated($q->paginate(24));
    }

    public function video(int $id)
    {
        $video = Video::published()->with('topic', 'series')->findOrFail($id);

        $related = $video->series_id
            ? Video::published()->with('topic', 'series')->where('series_id', $video->series_id)->orderBy('series_order')->orderBy('published_at')->get()
            : Video::published()->with('topic', 'series')->where('topic_id', $video->topic_id)->latest('published_at')->take(6)->get();

        return [
            'item' => (new ContentResource($video))->withDetail(),
            'related' => ContentResource::collection($related->where('id', '!=', $video->id)->values()),
        ];
    }

    // ---------- الصوتيات ----------

    public function audios(Request $request)
    {
        $q = Audio::published()->with('topic', 'series')
            ->when($this->topicId($request), fn ($q, $v) => $q->where('topic_id', $v))
            ->latest('published_at');

        return $this->paginated($q->paginate(50));
    }

    public function audio(int $id)
    {
        $audio = Audio::published()->with('topic', 'series')->findOrFail($id);

        $queue = $audio->series_id
            ? Audio::published()->with('topic', 'series')->where('series_id', $audio->series_id)->orderBy('series_order')->orderBy('published_at')->get()
            : Audio::published()->with('topic', 'series')->where('topic_id', $audio->topic_id)->latest('published_at')->take(20)->get();

        return [
            'item' => (new ContentResource($audio))->withDetail(),
            'queue' => ContentResource::collection($queue),
        ];
    }

    /** تسجيل تشغيل (للإحصائيات) */
    public function audioPlayed(int $id)
    {
        Audio::published()->whereKey($id)->increment('plays');

        return response()->noContent();
    }

    // ---------- الدروس والمنشورات ----------

    public function lessons(Request $request)
    {
        $q = Lesson::published()->with('topic', 'series')
            ->when($this->topicId($request), fn ($q, $v) => $q->where('topic_id', $v))
            ->latest('published_at');

        return $this->paginated($q->paginate(20));
    }

    public function lesson(int $id)
    {
        $lesson = Lesson::published()->with('topic', 'series')->findOrFail($id);
        $siblings = $lesson->series_id
            ? Lesson::published()->where('series_id', $lesson->series_id)->orderBy('series_order')->orderBy('published_at')->get(['id', 'title', 'series_order', 'published_at'])
            : collect();

        return ['item' => (new ContentResource($lesson))->withDetail()] + $this->neighbours($siblings, $lesson->id, 'lesson');
    }

    public function posts(Request $request)
    {
        $q = Post::published()->with('topic')
            ->when($this->topicId($request), fn ($q, $v) => $q->where('topic_id', $v))
            ->latest('published_at');

        return $this->paginated($q->paginate(20));
    }

    public function post(int $id)
    {
        return ['item' => (new ContentResource(Post::published()->with('topic')->findOrFail($id)))->withDetail()];
    }

    // ---------- السلاسل والمواضيع ----------

    public function seriesIndex()
    {
        return ['data' => SeriesResource::collection(Series::published()->with('topic')->orderBy('sort_order')->orderBy('id')->get())];
    }

    public function series(int $id)
    {
        $series = Series::published()->with('topic')->findOrFail($id);

        return [
            'series' => new SeriesResource($series),
            'items' => ContentResource::collection($series->publishedItems()->each->load('series')),
        ];
    }

    public function topic(string $slug, Request $request)
    {
        $topic = Topic::where('slug', $slug)->firstOrFail();
        $feed = Feed::paginate(['topic_id' => $topic->id], 30, max(1, (int) $request->page));

        return [
            'topic' => ['slug' => $topic->slug, 'name' => $topic->name, 'icon' => $topic->icon],
            'series' => SeriesResource::collection(Series::published()->with('topic')->where('topic_id', $topic->id)->orderBy('sort_order')->get()),
        ] + $this->paginated($feed);
    }

    // ---------- أدوات ----------

    private function featuredSeries()
    {
        $featured = Series::published()->with('topic')->where('is_featured', true)->orderBy('sort_order')->take(8)->get();

        return $featured->isNotEmpty() ? $featured : Series::published()->with('topic')->orderBy('sort_order')->take(4)->get();
    }

    private function topicId(Request $request): ?int
    {
        return $request->filled('topic') ? Topic::where('slug', $request->topic)->value('id') ?? -1 : null;
    }

    private function paginated(LengthAwarePaginator $p): array
    {
        return [
            'data' => ContentResource::collection($p->getCollection()),
            'meta' => ['page' => $p->currentPage(), 'lastPage' => $p->lastPage(), 'total' => $p->total()],
        ];
    }

    private function neighbours($siblings, int $id, string $type): array
    {
        $i = $siblings->search(fn ($s) => $s->id === $id);
        if ($i === false) {
            return ['position' => null, 'total' => 0, 'prev' => null, 'next' => null];
        }
        $pick = fn ($s) => $s ? ['id' => $s->id, 'title' => $s->title, 'type' => $type] : null;

        return [
            'position' => $i + 1,
            'total' => $siblings->count(),
            'prev' => $pick($siblings->get($i - 1)),
            'next' => $pick($siblings->get($i + 1)),
        ];
    }
}
