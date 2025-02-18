<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'color_id',
        'timezone_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'is_reminder',
        'reminder',
        'is_done',
        'user_ids',
        'location',
        'type',
        'is_all_day',
        'is_repeat',
        'is_busy',
        'path',
        'frequency',
        'date_space',
        'repeat_space',
        'end_repeat',
        'day_of_week',
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
        'user_ids'  => 'array',
        'reminder'  => 'array',
        'day_of_week'  => 'array',
        'day_of_month'  => 'array',
        'by_month'  => 'array',
        'exclude_time'  => 'array',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function color(){
        return $this->belongsTo(Color::class);
    }

    public function timezone() {
        return $this->belongsTo(Timezone::class);
    }
}
