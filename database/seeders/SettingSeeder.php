<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'user_id' => 1,
                'timezone_id' => 1, // Giả sử bạn đã có bản ghi timezone với id 1
                'language' => 'en',
                'theme' => 'light',
                'date_format' => 'd/m/Y',
                'time_format' => 'h:mmA',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 2,
                'timezone_id' => 2, // Giả sử bạn đã có bản ghi timezone với id 2
                'language' => 'en',
                'theme' => 'dark',
                'date_format' => 'd/m/Y',
                'time_format' => 'h:mmA',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 3,
                'timezone_id' => 3, // Giả sử bạn đã có bản ghi timezone với id 3
                'language' => 'vi',
                'theme' => 'light',
                'date_format' => 'd/m/Y',
                'time_format' => 'h:mmA',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 4,
                'timezone_id' => 4, // Giả sử bạn đã có bản ghi timezone với id 4
                'language' => 'en',
                'theme' => 'dark',
                'date_format' => 'd/m/Y',
                'time_format' => 'h:mmA',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 5,
                'timezone_id' => 5, // Giả sử bạn đã có bản ghi timezone với id 5
                'language' => 'vi',
                'theme' => 'light',
                'date_format' => 'd/m/Y',
                'time_format' => 'h:mmA',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insert($setting);
        }
    }
}
