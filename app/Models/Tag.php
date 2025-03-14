<?php

namespace App\Models;

use App\Mail\InviteGuestMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'description',
        'user_id',
        'color_code',
        'shared_user',
    ];

    protected $casts = [
        'shared_user' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function getSharedUsers()
    {
        return array_column($this->shared_user ?? [], 'user_id');
    }

    public function syncAttendeesWithTasks($oldSharedUsers)
    {
        $newSharedUsers = collect($this->shared_user)->pluck('user_id')->toArray();
    
        // Xác định những user đã bị xóa khỏi shared_user
        $removedUsers = array_diff($oldSharedUsers, $newSharedUsers);
        $addedUsers = array_diff($newSharedUsers, $oldSharedUsers);
    
        // Lấy danh sách Task liên quan đến Tag này
        $tasks = $this->tasks;
    
        foreach ($tasks as $task) {
            $attendees = is_array($task->attendees) ? $task->attendees : json_decode($task->attendees, true) ?? [];
    
            // Xóa người dùng bị xóa khỏi shared_user khỏi attendees
            $attendees = array_filter($attendees, function ($attendee) use ($removedUsers) {
                return !in_array($attendee['user_id'], $removedUsers);
            });
    
            // Thêm người dùng mới vào attendees
            foreach ($addedUsers as $userId) {
                $exists = collect($attendees)->contains('user_id', $userId);
                if (!$exists) {
                    $user = User::find($userId);
                    if ($user) {
                        $attendees[] = [
                            'user_id'    => $user->id,
                            'first_name' => $user->first_name,
                            'last_name'  => $user->last_name,
                            'email'      => $user->email,
                            'avatar'     => $user->avatar ?? null,
                            'status'     => 'pending',
                            'role'       => 'viewer',
                        ];
    
                        // Gửi email mời tham gia
                        Mail::to($user->email)->queue(new InviteGuestMail(Auth::user()->email, Auth::user()->name, $task));
                    }
                }
            }
    
            // Cập nhật lại attendees của Task
            $task->update(['attendees' => array_values($attendees)]);
        }
    }
    
}