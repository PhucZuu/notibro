<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class User_RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gán role cho admin
        $adminUser = DB::table('users')->where('email', 'admin@gmail.com')->first();
        DB::table('user_role')->insert([
            'user_id' => $adminUser->id,
            'role_id' => 1, 
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Gán role cho 4 user
        for ($i = 1; $i <= 4; $i++) {
            $user = DB::table('users')->where('email', "user{$i}@gmail.com")->first();
            DB::table('user_role')->insert([
                'user_id' => $user->id,
                'role_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
