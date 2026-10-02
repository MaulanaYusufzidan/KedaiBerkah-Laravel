<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SettingSeeder::class,
        ]);

        // Akun admin hanya dibuat jika kredensialnya diisi lewat environment.
        // Tidak ada password bawaan yang tertulis di repository.
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if ($email && $password) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => env('ADMIN_NAME', 'Admin Kedai Berkah'),
                'password' => Hash::make($password),
                'role' => 'admin',
            ])->save();
        }
    }
}
