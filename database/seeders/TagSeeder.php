<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Tag::create([
                'name' => 'Tag ' . $i,
                'description' => 'Description ' . $i,
                'user_id' => 1, // Sử dụng user_id có sẵn
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
