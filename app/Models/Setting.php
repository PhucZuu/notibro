<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'timezone_code',
        'language',
        'theme',
        'date_format',
        'time_format',
        'hour_format',
        'display_type',
        'is_display_dayoff',
        'tittle_format_option',
        'column_header_format_option',
        'first',
        'notification_type',
    ];

    protected $casts = [
        'tittle_format_options' => 'array',
        'column_header_format_option' => 'array'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($setting) {
            if (empty($setting->tittle_format_options)) {
                $setting->tittle_format_options = [
                    'year' => 'numeric',
                    'month' => 'long'
                ];
            }

            if (empty($setting->column_header_format_option)) {
                $setting->column_header_format_option = [
                    'weekday' => 'short',
                    'day'     => 'numeric',
                    'omitCommas' => true,
                ];
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
