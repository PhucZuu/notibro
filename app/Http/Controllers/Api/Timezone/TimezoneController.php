<?php

namespace App\Http\Controllers\Api\Timezone;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Timezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TimezoneController extends Controller
{
    public function index()
    {
        $timezones = Timezone::query()->withTrashed()->get();

        if(!$timezones) {
            return response()->json([
                'code'    => 404,
                'message' => "timezones not found"
            ],404);
        }

        return response()->json([
            'code'    => 200,
            'message' => "Retrieve timezones successfully",
            'data'    => $timezones,
        ],200);
    }

    public function show($id)
    {
        $timezone = Timezone::withTrashed()->find($id);
        
        if (!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => 'timezone not found',
            ]);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Retrieve timezone successfully',
            'data'    => $timezone,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => "required|max:255|unique:timezones",
            'utc_offset' => "required|max:255|unique:timezones",
        ]);

        try {
            $timezone = Timezone::create($data);

            return response()->json([
                'code'    => 200,
                'message' => "Created successfully",
                'data'    => $timezone,
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => "required|max:255|unique:timezones",
            'utc_offset' => "required|max:255|unique:timezones",
        ]);

        $timezone = Timezone::find($id);

        if(!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => 'timezone not found',
            ]);
        }

        try {
            $timezone->update($data);

            return response()->json([
                'code'    => 200,
                'message' => "Created successfully",
                'data'    => $timezone,
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function delete($id)
    {
        $timezone = Timezone::find($id);

        if(!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => "timezone not found",
            ],404);
        }

        try {
            $timezone->delete();

            return response()->json([
                'code' => 200,
                'message' => 'Delete timezone successfully',
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function forceDelete($id)
    {
        $timezone = Timezone::withTrashed()->find($id);

        if(!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => "timezone not found",
            ],404);
        }

        $settings = Setting::whereHas('timezones', function ($q) use ($timezone) {
            $q->where('timezone_id', $timezone->id);
        })->get();

        try {
            if($settings) {
                foreach ($settings as $setting) {
                    $setting->timezones()->detach($timezone->id);
                }
            }

            $timezone->forceDelete();

            return response()->json([
                'code' => 200,
                'message' => 'Delete timezone successfully',
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function restore($id)
    {
        $timezone = Timezone::withTrashed()->find($id);

        if(!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => "timezone not found",
            ],404);
        }

        try {
            $timezone->restore();

            return response()->json([
                'code' => 200,
                'message' => 'Restore timezone successfully',
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }
}
