<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TimezoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('timezones')->insert([
            [
                'name' => 'UTC-12:00',
                'utc_offset' => '-12:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-11:00',
                'utc_offset' => '-11:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-10:00',
                'utc_offset' => '-10:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-09:00',
                'utc_offset' => '-09:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-08:00',
                'utc_offset' => '-08:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-07:00',
                'utc_offset' => '-07:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-06:00',
                'utc_offset' => '-06:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-05:00',
                'utc_offset' => '-05:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-04:00',
                'utc_offset' => '-04:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-03:00',
                'utc_offset' => '-03:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-02:00',
                'utc_offset' => '-02:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC-01:00',
                'utc_offset' => '-01:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+00:00',
                'utc_offset' => '+00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+01:00',
                'utc_offset' => '+01:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+02:00',
                'utc_offset' => '+02:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+03:00',
                'utc_offset' => '+03:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+04:00',
                'utc_offset' => '+04:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+05:00',
                'utc_offset' => '+05:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+06:00',
                'utc_offset' => '+06:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+07:00',
                'utc_offset' => '+07:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+08:00',
                'utc_offset' => '+08:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+09:00',
                'utc_offset' => '+09:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+10:00',
                'utc_offset' => '+10:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+11:00',
                'utc_offset' => '+11:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UTC+12:00',
                'utc_offset' => '+12:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
