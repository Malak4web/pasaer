<?php

use App\Http\Controllers\Api\PublicController as P;
use Illuminate\Support\Facades\Route;

// API عام للواجهة — قراءة فقط
Route::middleware('throttle:120,1')->group(function () {
    Route::get('site', [P::class, 'site']);
    Route::get('home', [P::class, 'home']);
    Route::get('feed', [P::class, 'feed']);
    Route::get('search', [P::class, 'search']);

    Route::get('videos', [P::class, 'videos']);
    Route::get('videos/{id}', [P::class, 'video'])->whereNumber('id');

    Route::get('audios', [P::class, 'audios']);
    Route::get('audios/{id}', [P::class, 'audio'])->whereNumber('id');
    Route::post('audios/{id}/played', [P::class, 'audioPlayed'])->whereNumber('id')->middleware('throttle:20,1');

    Route::get('lessons', [P::class, 'lessons']);
    Route::get('lessons/{id}', [P::class, 'lesson'])->whereNumber('id');

    Route::get('posts', [P::class, 'posts']);
    Route::get('posts/{id}', [P::class, 'post'])->whereNumber('id');

    Route::get('series', [P::class, 'seriesIndex']);
    Route::get('series/{id}', [P::class, 'series'])->whereNumber('id');

    Route::get('topics/{slug}', [P::class, 'topic']);
});
