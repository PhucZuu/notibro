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

    public function syncAttendeesWithTasks($oldSharedUsers)
    {
        $newSharedUsers = collect($this->shared_user)->pluck('user_id')->toArray();
    
        $removedUsers = array_diff($oldSharedUsers, $newSharedUsers);
        $addedUsers = array_diff($newSharedUsers, $oldSharedUsers);
    
        $tasks = $this->tasks;
    
        foreach ($tasks as $task) {
            $attendees = is_array($task->attendees) ? $task->attendees : json_decode($task->attendees, true) ?? [];
    
            $attendees = array_filter($attendees, function ($attendee) use ($removedUsers) {
                return !in_array($attendee['user_id'], $removedUsers);
            });
    
            foreach ($addedUsers as $userId) {
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
                }
            }
            
    
            $task->update(['attendees' => array_values($attendees)]);
        }
    }
    
}