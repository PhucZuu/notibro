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
                'color_id' => 2,
                'timezone_id' => 1,
                'title' => 'Complete the project report.',
                'description' => 'This task involves completing the project report by the end of the week.',
                'start_time' => Carbon::now()->addHours(1),
                    'end_time' => Carbon::now()->addHours(2),
                'is_reminder' => 1,
                'reminder' => json_encode([['set_time' => 5, 'type' => 'email']]),
                'is_done' => 0,
                'user_ids' => json_encode([
                    ['user_id' => 2 ,'status' => 1, 'role' => 'editor'],
                    ['user_id' => 3 ,'status' => 1, 'role' => 'viewer'],
                ]),
                'location' => 'Office',
                'type' => 'task',
                'is_all_day' => false,
                'is_repeat' => true,
                'is_busy' => 0,
                'path' => null,
                'date_space' => 'weekly',
                'repeat_space' => 1,
                'end_repeat' => now()->addMonths(3),
                'day_of_week' => json_encode(['mo', 'we', 'fr']),
                'day_of_month' => json_encode([1, 15, 24]),
                'by_month' => json_encode([1, 6, 12]),
                'exclude_time' => json_encode(['2025-02-17']),
            ],
            [
                'user_id' => 2,
                'color_id' => 1,
                'timezone_id' => 1,
                'title' => 'Complete the project report.',
                'description' => 'This task involves completing the project report by the end of the week.',
                'start_time' => Carbon::now()->addHours(2),
                    'end_time' => Carbon::now()->addHours(3),
                'is_reminder' => 1,
                'reminder' => json_encode([
                    ['set_time' => 5, 'type' => 'email'],
                    ['set_time' => 15, 'type' => 'web'],
                ]),
                'is_done' => 0,
                'user_ids' => json_encode([
                    ['user_id' => 3 ,'status' => 1, 'role' => 'editor'],
                    ['user_id' => 4 ,'status' => 1, 'role' => 'viewer'],
                    ['user_id' => 5 ,'status' => 1, 'role' => 'viewer'],
                ]),
                'location' => 'Office',
                'type' => 'task',
                'is_all_day' => false,
                'is_repeat' => true,
                'is_busy' => 0,
                'path' => null,
                'date_space' => 'daily',
                'repeat_space' => 1,
                'end_repeat' => now()->addMonths(3),
                'day_of_week' => json_encode(['mo', 'fr']),
                'day_of_month' => json_encode([1, 15, 24]),
                'by_month' => json_encode([1, 6, 12]),
                'exclude_time' => json_encode(['2025-02-20']),
            ],
            // Bạn có thể thêm nhiều bản ghi hơn ở đây
        ]);
    }
}
