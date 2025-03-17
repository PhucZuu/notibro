<?php

namespace App\Http\Controllers\Api\Tag;

use App\Events\Tag\TagUpdatedEvetn;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\NotificationEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TagController extends Controller
{
    public function getRecipients($data)
    {
        Log::info('Xử lý người nhận');

        $recipients = collect($data)
            ->flatMap(fn($tag) => $tag->getSharedUserAndOwner()) // Lấy tất cả attendees
            ->unique() // Loại bỏ user trùng
            ->values() // Reset key của mảng
            ->toArray();

        return $recipients;
    }

    public function sendRealTimeUpdate($data, $action)
    {   
        Log::info('Xử lý gửi đi');

        $recipients = $this->getRecipients($data);

        event(new TagUpdatedEvetn($data, $action, $recipients));
    }

    public function index()
    {
        try {
            $userId = Auth::id();
    
            $ownedTags = Tag::where('user_id', $userId)->get();
    
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve tags successfully',
                'data'    => $ownedTags->isEmpty() ? [] : $ownedTags
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
    
            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving tags',
            ], 500);
        }
    }

    public function getSharedTag()
    {
        try {
            $userId = Auth::id();
    
            $sharedTags = Tag::whereJsonContains('shared_user', [['user_id' => $userId]])->get();
    
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve shared tags successfully',
                'data'    => $sharedTags->isEmpty() ? [] : $sharedTags
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
    
            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving shared tags',
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
            'shared_user'   => 'nullable',
        ]);

        try {
            $userId = Auth::id();

            if (Tag::where('user_id', $userId)->where('name', $validated['name'])->exists()) {
                return response()->json([
                    'code'    => 409,
                    'message' => 'You already have a tag with this name',
                ], 409);
            }

            $validated['user_id'] = $userId;

            if (!empty($validated['shared_user']) && is_string($validated['shared_user'])) {
                $validated['shared_user'] = json_decode($validated['shared_user'], true);
            }

            // $validated['shared_user'] = json_decode($validated['shared_user'], true) ?? [];

            $tag = Tag::create($validated);
 
            $returnTag[] = $tag;

            $this->sendRealTimeUpdate($returnTag, 'create');

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
            'name'         => 'required|string',
            'description'  => 'nullable|string',
            'color_code'   => 'nullable|string',
            'shared_user'  => 'nullable',
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
    
            $oldSharedUsers = collect($tag->shared_user)->pluck('user_id')->toArray();
    
            $tag->update([
                'name'        => $validated['name'],
                'description' => $validated['description'],
                'color_code'  => $validated['color_code'] ?? $tag->color_code,
                'shared_user' => $validated['shared_user'],
            ]);
    
            $tag->syncAttendeesWithTasks($oldSharedUsers);

            $returnTag[] = $tag;

            $this->sendRealTimeUpdate($returnTag, 'update');

            $newSharedUsers = collect($tag->shared_user)->pluck('user_id')->toArray();

            foreach($newSharedUsers as $userID){
                $user = User::find($userID);

                $user->notify(new NotificationEvent(
                    $user->id,
                    "Tag {$tag->name} vừa đưuọc cập nhật một chút, nhớ ngó qua nhé",
                    "",
                    "updated_tag"
                ));
            }
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag updated successfully',
                'data'    => $tag,
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
    
            $tasks = $tag->tasks;
    
            foreach ($tasks as $task) {
                $task->delete();
            }

            $returnTag[] = $tag;

            $this->sendRealTimeUpdate($returnTag, 'delete');

            $tag->delete();
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag and related tasks deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
    
            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while deleting tag and tasks',
            ], 500);
        }
    }    
}