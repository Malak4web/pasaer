<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if ($email && $password) {
            User::updateOrCreate(['email' => $email], [
                'name' => env('ADMIN_NAME', 'مدير الموقع'),
                'password' => $password,
                'role' => 'admin',
                'is_active' => true,
            ]);
            $this->command->info("Admin account ready: {$email} (password in .env)");
        } else {
            $this->command->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set — run: php artisan admin:create');
        }

        $this->call(DemoContentSeeder::class);
    }
}
