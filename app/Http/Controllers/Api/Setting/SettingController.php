<?php

namespace App\Http\Controllers\Api\Setting;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function setting()
    {
        $user_id = auth()->user()->id;

        $setting = Setting::where('user_id',$user_id)->first();

        if(!$setting) {
            return response()->json([
                'code'    => 404,
                'message' => "Setting not found",
            ],404);
        }

        return response()->json([
            'code'    => 200,
            'message' => "Success",
            'data'    => $setting,
        ]);
    }

    public function changeSetting(Request $request)
    {
        $setting = $request->validate([
            "timezone_code"   => ['required',Rule::exists('timezones','id')],
            "language"      => ['required',Rule::in(['en','vi'])],
            "theme"         => ['required',Rule::in(['light','dark'])],
            "date_format"   => ['required',Rule::in(['d/m/Y', 'm/d/Y', 'Y-m-d'])],
            "time_format"   => ['required',Rule::in(['h:mmA', 'HH:mm'])],
            'hour_format'   => ['required'],
            'display_type'  => ['required'],
            'is_display_dayoff' => ['required'],
            'tittle_format_option' => ['required'],
            'column_header_format_option' => ['required'],
            'first' => ['required'],
            'notification_type' => ['required'],
        ]);

        $userSetting = Setting::where('user_id', auth()->user()->id)->first();

        if(!$userSetting) {
            return response()->json([
                'code'    => 404,
                'message' => 'Setting not found',
            ]);
        }

        try {
            $userSetting->update($setting);

            return response()->json([
                'code'    => 200,
                'message' => 'Successful change',
                'data'    => $userSetting,
            ]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ],500);
        }
    }
}