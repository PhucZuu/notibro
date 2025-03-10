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
                'color_code' => '#FF0000',
                'timezone_code' => 'Asia/Ho_Chi_Minh',
                'tag_id' => 1,
                'title' => 'Complete the project report.',
                'description' => 'This task involves completing the project report by the end of the week.',
                'start_time' => Carbon::now()->addHours(1),
                'end_time' => Carbon::now()->addHours(2),
                'is_reminder' => 1,
                'reminder' => json_encode([['set_time' => 5, 'type' => 'email']]),
                'is_done' => 0,
                'attendees' => json_encode([
                    ['user_id' => 2 ,'status' => 'yes', 'role' => 'editor'],
                    ['user_id' => 3 ,'status' => 'yes', 'role' => 'viewer'],
                ]),
                'location' => 'Office',
                'type' => 'task',
                'is_all_day' => false,
                'is_repeat' => true,
                'is_busy' => 0,
                'path' => null,
                'freq' => 'daily',
                'interval' => 1,
                'until' => now()->addMonths(3),
                'byweekday' => null,
                'bymonthday' => null,
                'bymonth' => null,
                'bysetpos' => null,
                'exclude_time' => json_encode(['2025-02-21 24:00:00', '2025-02-23 24:00:00', '2025-02-28 24:00:00']),
                'count' => 30,
            ],
            [
                'user_id' => 2,
                'color_code' => '#FF0000',
                'timezone_code' => 'Asia/Ho_Chi_Minh',
                'tag_id' => 2,
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
                'attendees' => json_encode([
                    ['user_id' => 3 ,'status' => 'yes', 'role' => 'editor'],
                    ['user_id' => 4 ,'status' => 'yes', 'role' => 'viewer'],
                    ['user_id' => 5 ,'status' => 'yes', 'role' => 'viewer'],
                ]),
                'location' => 'Office',
                'type' => 'task',
                'is_all_day' => false,
                'is_repeat' => true,
                'is_busy' => 0,
                'path' => null,
                'freq' => null,
                'interval' => 1,
                'until' => now()->addMonths(3),
                'byweekday' => json_encode(['mo', 'fr']),
                'bymonthday' => null,
                'bymonth' => null,
                'bysetpos' => null,
                'exclude_time' => json_encode(['2025-02-22 24:00:00']),
                'count' => 30,
            ],
            [
                'user_id' => 2,
                'color_code' => '#FF0000',
                'timezone_code' => 'Europe/London',
                'tag_id' => 2,
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
                'attendees' => json_encode([
                    ['user_id' => 3 ,'status' => 'yes', 'role' => 'editor'],
                    ['user_id' => 4 ,'status' => 'yes', 'role' => 'viewer'],
                    ['user_id' => 5 ,'status' => 'pending', 'role' => 'viewer'],
                ]),
                'location' => 'Office',
                'type' => 'task',
                'is_all_day' => false,
                'is_repeat' => true,
                'is_busy' => 0,
                'path' => null,
                'freq' => null,
                'interval' => 1,
                'until' => now()->addMonths(3),
                'byweekday' => null,
                'bymonthday' => json_encode([1, 15, 24]),
                'bymonth' => null,
                'bysetpos' => null,
                'exclude_time' => json_encode(['2025-02-20 24:00:00']),
                'count' => 30,
            ],
            [
                'user_id' => 2,
                'color_code' => '#FF0000',
                'timezone_code' => 'Asia/Ho_Chi_Minh',
                'tag_id' => 2,
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
                'attendees' => json_encode([
                    ['user_id' => 3 ,'status' => 'yes', 'role' => 'editor'],
                    ['user_id' => 4 ,'status' => 'yes', 'role' => 'viewer'],
                    ['user_id' => 5 ,'status' => 'yes', 'role' => 'viewer'],
                ]),
                'location' => 'Office',
                'type' => 'task',
                'is_all_day' => false,
                'is_repeat' => true,
                'is_busy' => 0,
                'path' => null,
                'freq' => null,
                'interval' => 1,
                'until' => now()->addMonths(3),
                'byweekday' => null,
                'bymonthday' => null,
                'bymonth' => json_encode([1, 6, 12]),
                'bysetpos' => null,
                'exclude_time' => json_encode(['2025-02-21 24:00:00']),
                'count' => 30,
            ],
            [
                'user_id' => 2,
                'color_code' => '#FF0000',
                'timezone_code' => 'Europe/London',
                'tag_id' => 2,
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
                'attendees' => json_encode([
                    ['user_id' => 3 ,'status' => 'yes', 'role' => 'editor'],
                    ['user_id' => 4 ,'status' => 'yes', 'role' => 'viewer'],
                    ['user_id' => 5 ,'status' => 'yes', 'role' => 'viewer'],
                ]),
                'location' => 'Office',
                'type' => 'task',
                'is_all_day' => false,
                'is_repeat' => true,
                'is_busy' => 0,
                'path' => null,
                'freq' => 'daily',
                'interval' => 1,
                'until' => null,
                'byweekday' => null,
                'bymonthday' => null,
                'bymonth' => null,
                'bysetpos' => null,
                'count' => 30,
                'exclude_time' => json_encode(['2025-02-28 24:00:00']),
            ],
            // Bạn có thể thêm nhiều bản ghi hơn ở đây
        ]);
    }
}
