<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {--email=} {--name=}';

    protected $description = 'إنشاء حساب مدير للوحة التحكم (أو إعادة تعيين كلمة مروره)';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('البريد الإلكتروني');
        $name = $this->option('name') ?: $this->ask('الاسم', 'مدير الموقع');
        $password = $this->secret('كلمة المرور (8 أحرف على الأقل)');

        $v = Validator::make(compact('email', 'password'), [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => $name, 'password' => $password, 'role' => 'admin', 'is_active' => true,
        ]);
        $this->info("تم: {$email} مدير الآن.");

        return self::SUCCESS;
    }
}
