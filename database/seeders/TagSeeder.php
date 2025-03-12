<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = [1, 2, 3, 4, 5]; 

        foreach ($userIds as $userId) {
            for ($i = 1; $i <= 5; $i++) { 
                $sharedUsers = collect($userIds)
                    ->reject(fn($id) => $id === $userId) 
                    ->map(fn($id) => [
                        'user_id'    => $id,
                        'first_name' => "User{$id}",
                        'last_name'  => "Example",
                        'email'      => "user{$id}@gmail.com",
                        'avatar'     => "path/to/avatar{$id}.jpg",
                        'status'     => 'yes',
                        'role'       => ['viewer', 'editor'][array_rand(['viewer', 'editor'])],
                    ])
                    ->values()
                    ->toArray(); 

                Tag::create([
                    'name'         => "Tag {$i} for User {$userId}",
                    'description'  => "Description for Tag {$i} of User {$userId}",
                    'user_id'      => $userId,
                    'color_code'   => sprintf("#%06X", mt_rand(0, 0xFFFFFF)), 
                    'is_reminder'  => (bool)random_int(0, 1), 
                    'reminder'     => [
                        [
                            'type'     => 'email',
                            'set_time' => random_int(1, 60) 
                        ]
                    ], 
                    'shared_user'  => $sharedUsers, 
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }
}