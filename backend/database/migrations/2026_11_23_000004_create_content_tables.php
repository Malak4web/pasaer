<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** الأعمدة المشتركة بين كل أنواع المحتوى */
    private function common(Blueprint $table, bool $inSeries = true): void
    {
        $table->id();
        $table->string('title');
        $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
        if ($inSeries) {
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->unsignedInteger('series_order')->default(0);
        }
        $table->string('status', 20)->default('draft'); // draft | published
        $table->timestamp('published_at')->nullable();
        $table->text('search_index')->nullable(); // نص مُطبَّع للبحث العربي
        $table->timestamps();

        $table->index(['status', 'published_at']);
    }

    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $this->common($table);
            $table->string('youtube_id', 20)->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->default(0); // ثوانٍ
            $table->unsignedBigInteger('views')->default(0);
        });

        Schema::create('audios', function (Blueprint $table) {
            $this->common($table);
            $table->string('file_path')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedBigInteger('plays')->default(0);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $this->common($table);
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->unsignedSmallInteger('read_minutes')->default(1);
        });

        Schema::create('posts', function (Blueprint $table) {
            $this->common($table, inSeries: false);
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->unsignedSmallInteger('read_minutes')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('audios');
        Schema::dropIfExists('videos');
    }
};
