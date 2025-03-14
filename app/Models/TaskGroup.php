<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'task_id',
        'created_by',
    ];

    /**
     * Mối quan hệ: Nhóm thuộc về một Task.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Mối quan hệ: Người tạo nhóm là một User.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mối quan hệ: Nhóm có nhiều thành viên.
     */
    public function members()
    {
        return $this->hasMany(TaskGroupMember::class, 'group_id');
    }

    /**
     * Mối quan hệ: Nhóm có nhiều tin nhắn.
     */
    public function messages()
    {
        return $this->hasMany(TaskGroupMessage::class, 'group_id');
    }
}
