<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskGroupMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'message',
        'file',
    ];

    /**
     * Mối quan hệ: Tin nhắn thuộc về một nhóm.
     */
    public function group()
    {
        return $this->belongsTo(TaskGroup::class, 'group_id');
    }

    /**
     * Mối quan hệ: Người gửi tin nhắn là một User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
