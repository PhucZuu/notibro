<?php

namespace App\Http\Controllers\Api\Tag;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TagController extends Controller
{
    public function index()
    {
        try {
            $tags = Tag::where('user_id', Auth::id())->get();

            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve tags successfully',
                'data'    => $tags->isEmpty() ? [] : $tags
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving tags',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $tag = Tag::where('id', $id)->where('user_id', Auth::id())->first();

            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found or unauthorized',
                ], 404);
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Tag retrieved successfully',
                'data'    => $tag,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving the tag',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string',
            'description'   => 'nullable|string',
            'color_code'    => 'nullable|string',
            'shared_user'   => 'nullable|json',
        ]);

        try {
            $userId = Auth::id();

            // Kiểm tra xem đã có Tag cùng tên chưa
            if (Tag::where('user_id', $userId)->where('name', $validated['name'])->exists()) {
                return response()->json([
                    'code'    => 409,
                    'message' => 'You already have a tag with this name',
                ], 409);
            }

            $validated['user_id'] = $userId;
            $validated['shared_user'] = json_decode($validated['shared_user'], true) ?? [];

            $tag = Tag::create($validated);

            return response()->json([
                'code'    => 201,
                'message' => 'Tag created successfully',
                'data'    => $tag
            ], 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while creating tag',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'          => 'required|string',
            'description'   => 'nullable|string',
            'color_code'    => 'nullable|string',
            'shared_user'   => 'nullable|json',
        ]);

        try {
            $userId = Auth::id();
            $tag = Tag::where('id', $id)->where('user_id', $userId)->first();

            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found or unauthorized',
                ], 404);
            }

            // Kiểm tra xem đã có Tag khác cùng tên chưa
            if (Tag::where('user_id', $userId)->where('name', $validated['name'])->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'code'    => 409,
                    'message' => 'You already have a tag with this name',
                ], 409);
            }

            $tag->update([
                'name'          => $validated['name'],
                'description'   => $validated['description'],
                'color_code'    => $validated['color_code'],
                'shared_user'   => json_decode($validated['shared_user'], true) ?? [],
            ]);

            return response()->json([
                'code'    => 200,
                'message' => 'Tag updated successfully',
                'data'    => $tag
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while updating tag',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $tag = Tag::where('id', $id)->where('user_id', Auth::id())->first();

            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found or unauthorized',
                ], 404);
            }

            $tag->delete();

            return response()->json([
                'code'    => 200,
                'message' => 'Tag deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while deleting tag',
            ], 500);
        }
    }
}