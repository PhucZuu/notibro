<?php

namespace App\Models;

use App\Mail\InviteGuestMail;
use App\Notifications\NotificationEvent;
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
        'reminder' => 'array',
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
        return collect($this->shared_user ?? [])->pluck('user_id')->toArray();
    }    

    public function getSharedUserAndOwner()
    {
        $filteredSharedUser = collect($this->shared_user ?? [])
            ->where('status', 'yes')
            ->pluck('user_id')
            ->toArray();
    
        return array_merge([$this->user_id], $filteredSharedUser);
    }    


    public function syncAttendeesWithTasks(array $oldSharedUsers)
    {
        $newSharedUsers = collect($this->shared_user)->keyBy('user_id');
        $tasks = $this->tasks;
    
        foreach ($tasks as $task) {
            $attendees = collect(is_array($task->attendees) ? $task->attendees : json_decode($task->attendees, true) ?? [])
                ->keyBy('user_id');
    
            $updatedAttendees = [];
    
            foreach ($newSharedUsers as $userId => $sharedUserInfo) {
                $user = User::find($userId);
                if (!$user) continue;
    
                $newAttendee = [
                    'user_id'    => $user->id,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'email'      => $user->email,
                    'avatar'     => $user->avatar ?? null,
                    'status'     => $sharedUserInfo['status'] ?? 'pending',
                    'role'       => $sharedUserInfo['role'] ?? 'viewer',
                ];
    
                $existingAttendee = $attendees->get($userId);
    
                // Nếu attendee mới khác với attendee cũ (so sánh từng giá trị)
                if (!$existingAttendee || $existingAttendee !== $newAttendee) {
                    $updatedAttendees[$userId] = $newAttendee;
                } else {
                    $updatedAttendees[$userId] = $existingAttendee;
                }
            }
    
            // Chỉ giữ lại những attendee nằm trong shared_user (những user bị xóa sẽ bị loại bỏ)
            $task->update([
                'attendees' => array_values($updatedAttendees),
            ]);
        }
    }
    

}