<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'tag_id',
        'color_code',
        'timezone_code',
        'title',
        'description',
        'start_time',
        'end_time',
        'is_reminder',
        'reminder',
        'is_done',
        'attendees',
        'location',
        'type',
        'is_all_day',
        'is_repeat',
        'is_busy',
        'path',
        'freq',
        'interval',
        'until',
        'count',
        'byweekday',
        'bymonthday',
        'bymonth',
        'bysetpos',
        'exclude_time',
        'parent_id'
    ];

    protected $attributes = [
        'is_reminder'   => 0,
        'is_done'       => 0,
        'is_all_day'    => 0,
        'is_repeat'     => 0,
        'is_busy'       => 0,
    ];

    protected $casts = [
        'attendees'  => 'array',
        'reminder'  => 'array',
        'byweekday'  => 'array',
        'bymonthday'  => 'array',
        'bymonth'  => 'array',
        'bysetpos'  => 'array',
        'exclude_time'  => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($task) {
            if (!$task->uuid) {
                $task->uuid = Str::uuid();
            }
        });
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function tag(){
        return $this->belongsTo(Tag::class);
    }

    public function getAttendees()
    {
        $data = array_merge([$this->user_id], array_column($this->attendees ?? [], 'user_id'));
        
        return $data;
    }
}
