<?php

use App\Http\Controllers\Admin\AudioController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SeriesController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware(['auth', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('account', [AuthController::class, 'editAccount'])->name('account');
        Route::put('account', [AuthController::class, 'updateAccount'])->name('account.update');

        // المحتوى: المدير والمحرر
        Route::resource('videos', VideoController::class)->except('show');
        Route::get('audios/organize', [AudioController::class, 'organize'])->name('audios.organize');
        Route::post('audios/recategorize', [AudioController::class, 'recategorize'])->name('audios.recategorize');
        Route::resource('audios', AudioController::class)->except('show');
        Route::resource('lessons', LessonController::class)->except('show');
        Route::resource('posts', PostController::class)->except('show');
        Route::resource('series', SeriesController::class)->except('show')->parameters(['series' => 'series']);
        Route::resource('topics', TopicController::class)->except('show');
        Route::post('{resource}/{id}/toggle', [DashboardController::class, 'toggleStatus'])
            ->whereIn('resource', ['videos', 'audios', 'lessons', 'posts'])
            ->name('toggle');

        // للمدير فقط
        Route::middleware('admin')->group(function () {
            Route::resource('users', UserController::class)->except('show');
            Route::get('settings', [SettingController::class, 'edit'])->name('settings');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        });
    });
});

// يحتاجه middleware المصادقة الافتراضي
Route::get('login', fn () => redirect()->route('admin.login'))->name('login');
