<?php

namespace App\Http\Controllers\Admin;

use App\Models\Audio;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
}
