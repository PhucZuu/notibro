<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileEntry extends Model
{
    use HasFactory;

    protected $table = 'file_entries';

    protected $fillable = [
        'file_name',
        'client_name',
        'extension',
        'size',
        'mime',
        'task_id',
        'owner_id',
    ];

    /**
     * Get the task associated with the file entry.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the owner of the file.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
