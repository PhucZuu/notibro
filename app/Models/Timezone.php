<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Timezone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'utc_offset',
    ];

    // public function task(){
    //     return $this->hasMany(Task::class);
    // }

    // public function setting(){
    //     return $this->hasMany(Setting::class);
    // }
}
