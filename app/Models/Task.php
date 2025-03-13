<?php

namespace App\Models;

use Carbon\Carbon;
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }

    public function getAttendees()
    {
        $filteredAttendees = collect($this->attendees ?? [])
        ->where('status', 'yes') // Chỉ lấy những attendee có status = 'yes'
        ->pluck('user_id') // Lấy user_id
        ->toArray();

        $data = array_merge([$this->user_id], $filteredAttendees);

        return $data;
    }

    public function getAttendeesForRealTime()
    {
        $filteredAttendees = collect($this->attendees ?? [])
        ->pluck('user_id') // Lấy user_id
        ->toArray();

        $data = array_merge([$this->user_id], $filteredAttendees);

        return $data;
    }

    public function formatedReturnData()
    {
        $timezone_code = $this->timezone_code;

        $formattedTask = $this->toArray();

        // Format rrule
        $formattedTask['rrule'] = [
            'freq'      => $this->freq,
            'interval'  => $this->interval,
            'until'     => $this->until ? Carbon::parse($this->until, 'UTC') : null,
            'count'     => $this->count,
            'byweekday' => $this->byweekday,
            'bymonthday' => $this->bymonthday,
            'bymonth'   => $this->bymonth,
            'bysetpos'  => $this->bysetpos,
        ];

        // Format thời gian
        $formattedTask['start_time'] = Carbon::parse($this->start_time, 'UTC');
        $formattedTask['end_time'] = $this->end_time ? Carbon::parse($this->end_time, 'UTC') : null;

        // Format exclude_time
        if ($this->exclude_time && count($this->exclude_time) > 0) {
            $formattedTask['exclude_time'] = array_map(fn($date) => Carbon::parse($date, 'UTC'), $this->exclude_time);
        }

        // Format attendees
        $formattedTask['attendees'] = [];
        if ($this->attendees) {
            foreach ($this->attendees as $attendee) {
                $user = User::select('first_name', 'last_name', 'email', 'avatar')
                    ->where('id', $attendee['user_id'])
                    ->first();

                if ($user) {
                    $formattedTask['attendees'][] = [
                        'user_id'    => $attendee['user_id'],
                        'first_name' => $user->first_name,
                        'last_name'  => $user->last_name,
                        'email'      => $user->email,
                        'avatar'     => $user->avatar,
                        'status'     => $attendee['status'],
                        'role'       => $attendee['role'],
                    ];
                }
            }
        }

        // Xóa các trường không cần thiết
        unset(
            $formattedTask['freq'],
            $formattedTask['interval'],
            $formattedTask['until'],
            $formattedTask['count'],
            $formattedTask['byweekday'],
            $formattedTask['bymonthday'],
            $formattedTask['bymonth'],
            $formattedTask['bysetpos']
        );

        return $formattedTask;
    }
}
