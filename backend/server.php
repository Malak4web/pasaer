<?php

/**
 * راوتر لسيرفر PHP المدمج مع حدود رفع أكبر لملفات الصوت.
 * التشغيل (من مجلد backend):
 *   composer serve
 * أو:
 *   php -d upload_max_filesize=256M -d post_max_size=260M -S 127.0.0.1:8000 -t public server.php
 *
 * على سيرفر الإنتاج اضبط upload_max_filesize و post_max_size في php.ini بدل الملف ده.
 */
chdir(__DIR__.'/public');

// لازم نرجّع نتيجة الـ require: لو رجّع false السيرفر يخدم الملف الثابت (CSS/JS/الصوت) مباشرة
return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
