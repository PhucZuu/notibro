<?php

namespace App\Http\Controllers\Api\Color;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Color;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ColorController extends Controller
{
    public function index()
    {
        try {
            $colors = Color::all();
            if ($colors->isEmpty()) {
                return response()->json([
                    'code'    => 200,
                    'message' => 'No colors available',
                    'data'    => []
                ], 200);
            }
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve Color successfully',
                'data'    => $colors
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving colors',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:Colors,name',
            'code' => 'required|string|size:7|unique:Colors,code',
        ]);

        try {
            $color = Color::create($validated);

            return response()->json([
                'code'    => 201,
                'message' => 'Color created successfully',
                'data'    => $color
            ], 201);
        } catch (\Exception $e) {
             
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while creating color',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $color = Color::find($id);
            if(!$color){
                return response()->json([
                    'code'    => 404,
                    'message' => 'Color not found',
                ], 404);
            }
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve color successfully',
                'data'    => $color
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:Colors,name,' . $id,
            'code' => 'required|string|size:7|unique:Colors,code,' . $id,
        ]);

        try {
            $color = Color::find($id);
            $color->update($validated);


            return response()->json([
                'code'    => 200,
                'message' => 'Color updated successfully',
                'data'    => $color
            ], 200);
        } catch (\Exception $e) {
             
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while updating color',
            ], 500);
        }
    }

    public function destroy($id)
    {
         
        try {
            $color = Color::find($id);
            $color->delete();


            return response()->json([
                'code'    => 200,
                'message' => 'Color deleted successfully',
                'data'    => null
            ], 200);
        } catch (\Exception $e) {
             
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while deleting color',
            ], 500);
        }
    }

    public function restore($id)
    {

        try {
            // Lấy bản ghi đã bị xóa mềm
            $color = Color::withTrashed()->find($id);

            // Khôi phục bản ghi
            $color->restore();

            return response()->json([
                'code'    => 200,
                'message' => 'Color restored successfully',
                'data'    => $color
            ], 200);
        } catch (\Exception $e) {
             
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while restoring color',
            ], 500);
        }
    }

    public function destroyPermanent($id)
    {
         
        try {
            $color = Color::withTrashed()->find($id);
            $color->forceDelete();
            return response()->json([
                'code'    => 200,
                'message' => 'Color permanently deleted successfully',
                'data'    => null
            ], 200);
        } catch (\Exception $e) {
             
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while permanently deleting color',
            ], 500);
        }
    }
}
