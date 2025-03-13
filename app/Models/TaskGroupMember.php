<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'role'
    ];

    /**
     * Mối quan hệ: Thành viên thuộc về một nhóm.
     */
    public function group()
    {
        return $this->belongsTo(TaskGroup::class, 'group_id');
    }

    /**
     * Mối quan hệ: Thành viên là một User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
