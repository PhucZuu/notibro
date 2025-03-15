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
        'reply_to'
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

    public function replyMessage()
    {
        return $this->belongsTo(TaskGroupMessage::class, 'reply_to');
    }

    public function replies()
{
    return $this->hasMany(TaskGroupMessage::class, 'reply_to');
}

}
