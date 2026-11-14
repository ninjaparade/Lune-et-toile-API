<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $yaz = User::factory()->create([
            'name' => 'Yaz Jallad',
            'username' => 'yaz',
            'email' => 'yazjallad@gmail.com',
            'password' => 'password',
        ]);

        $andy = User::factory()->create([
            'name' => 'Andrea Dunn',
            'username' => 'andy',
            'email' => 'andydunn001@gmail.com',
            'password' => 'password',
        ]);

        $yaz->befriend($andy);
    }
}
