<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'description',
        'user_id',
        'color_code',
        'is_reminder',
        'reminder',
        'shared_user',
    ];

    protected $attributes = [
        'is_reminder' => 0,
    ];

    protected $casts = [
        'reminder'    => 'array',
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
        return array_column($this->shared_user ?? [], 'user_id');
    }
}