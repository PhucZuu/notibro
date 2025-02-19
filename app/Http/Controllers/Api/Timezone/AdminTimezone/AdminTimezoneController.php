<?php

namespace App\Http\Controllers\Api\Timezone\AdminTimezone;

use App\Http\Controllers\Controller;
use App\Models\Timezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminTimezoneController extends Controller
{
    public function index(Request $request)
    {
        $timezones = Timezone::query()
            ->withTrashed()
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'code'    => 200,
            'message' => 'Retrieve timezones successfully',
            'data'    => $timezones,
        ], 200);
    }

    public function show($id)
    {
        $timezone = Timezone::withTrashed()->find($id);

        if (!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => 'Timezone not found',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Retrieve timezone successfully',
            'data'    => $timezone,
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => "required|max:255|unique:timezones",
            'utc_offset' => "required|max:255|unique:timezones",
        ]);

        try {
            $timezone = Timezone::create($data);

            return response()->json([
                'code'    => 201,
                'message' => 'Created successfully',
                'data'    => $timezone,
            ], 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $timezone = Timezone::findOrFail($id);

        if (!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => 'Timezone not found',
            ], 404);
        }

        $data = $request->validate([
            // 'name'       => "sometimes|required|max:255|unique:timezones,name,{$id}",
            // 'utc_offset' => "sometimes|required|max:255|unique:timezones,utc_offset,{$id}",
        ]);

        try {
            $timezone->update($data);

            return response()->json([
                'code'    => 200,
                'message' => 'Updated successfully',
                'data'    => $timezone,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }

    public function delete($id)
    {
        $timezone = Timezone::find($id);

        if (!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => 'Timezone not found',
            ], 404);
        }

        try {
            $timezone->delete();

            return response()->json([
                'code'    => 200,
                'message' => 'Deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }

    public function restore($id)
    {
        $timezone = Timezone::onlyTrashed()->findOrFail($id);


        if (!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => 'Timezone not found in trash',
            ], 404);
        }

        try {
            $timezone->restore();

            return response()->json([
                'code'    => 200,
                'message' => 'Restored successfully',
                'data'    => $timezone,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }

    public function forceDelete($id)
    {
        $timezone = Timezone::onlyTrashed()->find($id);

        if (!$timezone) {
            return response()->json([
                'code'    => 404,
                'message' => 'Timezone not found in trash',
            ], 404);
        }

        try {
            $timezone->forceDelete();

            return response()->json([
                'code'    => 200,
                'message' => 'Permanently deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }
}