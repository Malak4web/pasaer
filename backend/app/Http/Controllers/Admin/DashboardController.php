<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audio;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\Series;
use App\Models\Video;

class DashboardController extends Controller
{
    /** نوع المحتوى → [الموديل، التسمية، الأيقونة] */
    public const TYPES = [
        'videos' => [Video::class, 'مرئي', 'ti-player-play'],
        'audios' => [Audio::class, 'صوتي', 'ti-headphones'],
        'lessons' => [Lesson::class, 'درس', 'ti-book'],
        'posts' => [Post::class, 'منشور', 'ti-notes'],
    ];

    public function __invoke()
    {
        $stats = collect(self::TYPES)->map(fn ($t, $key) => [
            'label' => ['videos' => 'المرئيات', 'audios' => 'الصوتيات', 'lessons' => 'الدروس', 'posts' => 'المنشورات'][$key],
            'icon' => $t[2],
            'total' => $t[0]::count(),
            'drafts' => $t[0]::where('status', 'draft')->count(),
        ]);

        // آخر ما تم تعديله من كل الأنواع
        $recent = collect(self::TYPES)
            ->flatMap(fn ($t, $key) => $t[0]::with('topic')->latest('updated_at')->take(6)->get()
                ->map(fn ($m) => ['resource' => $key, 'label' => $t[1], 'icon' => $t[2], 'item' => $m]))
            ->sortByDesc(fn ($r) => $r['item']->updated_at)
            ->take(8)
            ->values();

        $scheduled = collect(self::TYPES)
            ->flatMap(fn ($t, $key) => $t[0]::where('status', 'published')->where('published_at', '>', now())->get()
                ->map(fn ($m) => ['resource' => $key, 'label' => $t[1], 'item' => $m]))
            ->sortBy(fn ($r) => $r['item']->published_at)
            ->values();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'scheduled' => $scheduled,
            'seriesCount' => Series::count(),
            'totalViews' => Video::sum('views'),
        ]);
    }

    /** نشر / إرجاع لمسودة بضغطة من الجداول */
    public function toggleStatus(string $resource, int $id)
    {
        $item = self::TYPES[$resource][0]::findOrFail($id);
        $item->status = $item->status === 'published' ? 'draft' : 'published';
        $item->save();

        return back()->with('success', $item->status === 'published' ? 'تم النشر.' : 'تم التحويل لمسودة.');
    }
}
