<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('colors')->insert([
            [
                'name' => 'Red',
                'code' => '#FF0000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Green',
                'code' => '#00FF00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Blue',
                'code' => '#0000FF',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Yellow',
                'code' => '#FFFF00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cyan',
                'code' => '#00FFFF',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Magenta',
                'code' => '#FF00FF',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Bạn có thể thêm nhiều màu hơn ở đây
        ]);
    }
}
