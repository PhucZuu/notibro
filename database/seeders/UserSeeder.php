<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Tạo admin
         DB::table('users')->insert([
            'email' => 'admin@gmail.com',
            'password' => Hash::make('admin123456'),
            'avatar' => 'path/to/avatar.jpg',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'male',
            'address' => 'Testing ABC',
            'phone' => '0000000000',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tạo 4 user
        for ($i = 1; $i <= 4; $i++) {
            DB::table('users')->insert([
                'email' => "user{$i}@gmail.com",
                'password' => Hash::make('user123456'),
                'avatar' => 'path/to/avatar.jpg',
                'first_name' => "User{$i}",
                'last_name' => 'Example',
                'gender' => $i % 2 == 0 ? 'female' : 'male',
                'address' => 'Testing ABC',
                'phone' => '0000000000',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
