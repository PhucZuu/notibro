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

            if ($tags->isEmpty()) {
                return response()->json([
                    'code'    => 200,
                    'message' => 'No tags available',
                    'data'    => []
                ], 200);
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve tags successfully',
                'data'    => $tags
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving tags',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (Tag::where('name', $value)->where('user_id', Auth::id())->exists()) {
                        $fail('The tag name has already been taken.');
                    }
                },
            ],
            'description' => 'nullable|string',
        ]);

        try {
            $validated['user_id'] = Auth::id(); 
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

    public function show($id)
    {
        try {
            $tag = Tag::where('id', $id)->where('user_id', Auth::id())->first();

            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found',
                ], 404);
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve tag successfully',
                'data'    => $tag
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving tag',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($id) {
                    if (Tag::where('name', $value)
                            ->where('user_id', Auth::id())
                            ->where('id', '!=', $id)
                            ->exists()) {
                        $fail('The tag name has already been taken.');
                    }
                },
            ],
            'description' => 'nullable|string',
        ]);

        try {
            $tag = Tag::where('id', $id)->where('user_id', Auth::id())->first();

            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found or unauthorized',
                ], 404);
            }

            $tag->update($validated);

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
                'data'    => null
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