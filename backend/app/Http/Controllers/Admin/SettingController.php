<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', [
            'values' => Setting::allValues(),
            'fields' => Setting::DEFAULTS,
            'videos' => Video::published()->latest('published_at')->get(['id', 'title']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'speaker' => ['nullable', 'string', 'max:100'],
            'youtube_channel_url' => ['nullable', 'url', 'max:255'],
            'verse_text' => ['nullable', 'string', 'max:255'],
            'verse_ref' => ['nullable', 'string', 'max:100'],
            'featured_video_id' => ['nullable', 'exists:videos,id'],
        ]);

        Setting::put($data);

        return back()->with('success', 'تم حفظ الإعدادات.');
    }
}
