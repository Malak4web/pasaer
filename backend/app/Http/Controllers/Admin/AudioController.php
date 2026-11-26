<?php

namespace App\Http\Controllers\Admin;

use App\Models\Audio;
use App\Models\Series;
use App\Models\Topic;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AudioController extends CrudController
{
    protected string $model = Audio::class;
    protected string $resource = 'audios';
    protected string $singular = 'الصوتية';
    protected array $with = ['topic', 'series'];

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,audio/wav,audio/x-wav,audio/webm', 'max:204800'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'remove_file' => ['nullable', 'boolean'],
            'duration' => ['nullable', 'regex:/^\d{1,2}(:\d{1,2}){0,2}$/'],
        ] + $this->publishingRules();
    }

    protected function messages(): array
    {
        return [
            'file.mimetypes' => 'الملف لازم يكون صوت (mp3, m4a, ogg, wav).',
            'file.max' => 'أقصى حجم للملف ٢٠٠ ميجا.',
            'file.uploaded' => 'الرفع فشل، غالبًا الملف أكبر من حد السيرفر (upload_max_filesize).',
            'duration.regex' => 'اكتب المدة بالشكل 28:14 أو 1:02:05.',
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item): array
    {
        $data['duration'] = Arabic::parseDuration($data['duration'] ?? '');
        $data['series_order'] = (int) ($data['series_order'] ?? 0);

        if ($request->hasFile('file')) {
            if ($item?->file_path) {
                Storage::disk('public')->delete($item->file_path);
            }
            $data['file_path'] = $request->file('file')->store('audios/'.now()->format('Y/m'), 'public');
        } elseif ($request->boolean('remove_file') && $item?->file_path) {
            Storage::disk('public')->delete($item->file_path);
            $data['file_path'] = null;
        }

        unset($data['file'], $data['remove_file']);

        return $data;
    }

    /** صفحة إعادة تصنيف الصوتيات بالسحب والإفلات */
    public function organize(): View
    {
        $topics = Topic::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'icon']);
        $seriesList = Series::where('kind', 'audio')->orderBy('sort_order')->orderBy('title')->get(['id', 'title', 'topic_id']);
        $audios = Audio::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'topic_id', 'series_id', 'series_order', 'duration', 'published_at', 'status'])
            ->map(fn (Audio $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'topic_id' => $a->topic_id,
                'series_id' => $a->series_id,
                'series_order' => $a->series_order,
                'duration' => Arabic::formatDuration($a->duration),
                'date' => $a->published_at?->format('Y-m-d') ?? '',
                'status' => $a->status,
                'edit_url' => route('admin.audios.edit', $a),
            ]);

        return view('admin.audios.organize', compact('topics', 'seriesList', 'audios'));
    }

    /** حفظ إعادة التصنيف (سحب وإفلات) عبر AJAX */
    public function recategorize(Request $request): JsonResponse
    {
        $field = $request->input('field');
        if (! in_array($field, ['topic_id', 'series_id'], true)) {
            return response()->json(['ok' => false, 'message' => 'حقل التصنيف غير صالح.'], 422);
        }

        $table = $field === 'topic_id' ? 'topics' : 'series';
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:audios,id'],
            'value' => ['nullable', 'integer', "exists:{$table},id"],
        ]);

        $value = $data['value'] ?? null;
        $update = [$field => $value];

        // عند نقل صوتية إلى سلسلة لها موضوع محدد، نربطها بنفس الموضوع تلقائيًا لو لم يكن لها موضوع
        if ($field === 'series_id' && $value !== null) {
            $seriesTopicId = Series::whereKey($value)->value('topic_id');
            if ($seriesTopicId) {
                $update['topic_id'] = $seriesTopicId;
            }
        }

        $updated = Audio::whereIn('id', $data['ids'])->update($update);

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'applied' => $update,
        ]);
    }
}
