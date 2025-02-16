<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tasks')->insert([
            [
                'user_id' => 1,
                'color_id' => 1,
                'timezone_id' => 1,
                'title' => 'Complete Test task 1',
                'description' => 'Complete the project report.',
                'is_reminder' => true,
                'reminder' => json_encode([
                    ['set_time' => 5, 'type' => 'web'],
                    ['set_time' => 20, 'type' => 'email']
                ]),
                'start_time' => Carbon::now()->addHours(2),
                'end_time' => Carbon::now()->addHours(4),
                'user_ids' => json_encode([
                    ['user_id' => 2 ,'status' => 1, 'role' => 'editor'],
                    ['user_id' => 3 ,'status' => 1, 'role' => 'viewer']
                ]),
                'is_all_day' => false,
                'is_busy' => false,
                'date_space' => 'weekly',
                'repeat_space' => 1,
                'end_repeat' => Carbon::now()->addMonth(),
                'day_of_week' => null,
                'exclude_time' => json_encode(['2023-10-01', '2023-10-05']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 2,
                'color_id' => 2,
                'timezone_id' => 1,
                'title' => 'Prepare Meeting Test task 1',
                'description' => 'Prepare for the meeting.',
                'is_reminder' => true,
                'reminder' => json_encode([
                    ['set_time' => 15, 'type' => 'email'],
                    ['set_time' => 20, 'type' => 'web']
                ]),
                'start_time' => Carbon::now()->addHours(1),
                'end_time' => Carbon::now()->addHours(2),
                'user_ids' => json_encode([
                    ['user_id' => 3 ,'status' => 1, 'role' => 'editor'],
                    ['user_id' => 4 ,'status' => 1, 'role' => 'viewer'],
                    ['user_id' => 5 ,'status' => 0, 'role' => '']
                ]),
                'is_all_day' => true,
                'is_busy' => true,
                'date_space' => 'monthly',
                'repeat_space' => 2,
                'end_repeat' => Carbon::now()->addMonths(3),
                'day_of_week' => '1,3', // Thứ Hai và Thứ Tư
                'exclude_time' => json_encode(['2023-10-10']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Bạn có thể thêm nhiều bản ghi hơn ở đây
        ]);
    }
}
