<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoragePackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'storage_packages';
}
