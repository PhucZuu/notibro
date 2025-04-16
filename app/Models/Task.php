<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tasks';

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
        'parent_id',
        'link',
        'is_private',
        'default_permission',
        // 'is_trash',
    ];

    protected $attributes = [
        'is_reminder'   => 0,
        'is_done'       => 0,
        'is_all_day'    => 0,
        'is_repeat'     => 0,
        'is_busy'       => 0,
        'is_private'    => 0,
        // 'is_trash'      => 0,
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

    public static function getTableStructure()
    {
        $columns = Schema::getColumnListing((new self)->getTable());

        // Định nghĩa mô tả rõ ràng hơn cho từng cột
        $descriptions = [
            // 'uuid'          => 'uuidv4',
            'tag_id'        => 'required|integer',
            'color_code'    => 'required|string|max:10|in:#ff4d4f,#52c41a,#1890ff,#faad14,#722ed1,#bfbfbf,#fa541c,#eb2f96,#a97c50,#13c2c2,#237804,#003a8c',
            'timezone_code' => 'required|string|max:50',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'start_time'    => 'required|date_format:Y-m-d H:i:s',
            'end_time'      => 'required|date_format:Y-m-d H:i:s|after_or_equal:start_time',
            'is_reminder'   => 'required|boolean|in:0,1',
            'reminder'      => 'nullable|array',
            'is_done'       => 'required|boolean|in:0,1',
            // 'attendees'     => 'nullable|array',
            'location'      => 'nullable|string|max:255',
            'type'          => 'required|in:event,task,appointment',
            'is_all_day'    => 'nullable|boolean|in:0,1',
            'is_repeat'     => 'nullable|boolean|in:0,1',
            'is_busy'       => 'required|boolean|in:0,1',
            'path'          => 'nullable|string',
            'freq'          => 'nullable|in:daily,weekly,monthly,yearly',
            'interval'      => 'nullable|integer|min:1',
            'until'         => 'nullable|date_format:Y-m-d H:i:s|after:start_time',
            'count'         => 'nullable|integer|min:1',
            'byweekday'     => 'nullable|array|in:MO,Tu,WE,TH,FR,SA,SU',
            'bymonthday'    => 'nullable|array',
            'bymonth'       => 'nullable|array',
            'bysetpos'      => 'nullable|array',
            'exclude_time'  => 'nullable|array|date_format:Y-m-d H:i:s',
            'parent_id'     => 'nullable|integer',
            'is_private'    => 'required|boolean|in:0,1',
        ];

        return array_combine($columns, array_map(fn($col) => $descriptions[$col] ?? "Không có mô tả", $columns));
    }
}
