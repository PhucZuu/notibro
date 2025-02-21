<?php

namespace Database\Seeders;

use Carbon\Carbon;
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
        $timeZones = [
            ['name' => 'Pacific/Midway', 'utc_offset' => '-11:00'],
            ['name' => 'America/Adak', 'utc_offset' => '-10:00'],
            ['name' => 'America/Los_Angeles', 'utc_offset' => '-08:00'],
            ['name' => 'America/Chicago', 'utc_offset' => '-06:00'],
            ['name' => 'America/New_York', 'utc_offset' => '-05:00'],
            ['name' => 'Europe/London', 'utc_offset' => '+00:00'],
            ['name' => 'Europe/Berlin', 'utc_offset' => '+01:00'],
            ['name' => 'Asia/Tokyo', 'utc_offset' => '+09:00'],
            ['name' => 'Australia/Sydney', 'utc_offset' => '+10:00'],
            ['name' => 'Asia/Ho_Chi_Minh', 'utc_offset' => '+07:00'],
        ];

        foreach ($timeZones as $timeZone) {
            DB::table('timezones')->insert([
                'name' => $timeZone['name'],
                'utc_offset' => $timeZone['utc_offset'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null, // Nếu bạn sử dụng soft deletes
            ]);
        }
    }
}
