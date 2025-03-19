<?php

namespace App\Http\Controllers\Api\Tag;

use App\Events\Tag\TagUpdatedEvetn;
use App\Http\Controllers\Controller;
use App\Mail\InviteToTagMail;
use Illuminate\Http\Request;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\NotificationEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TagController extends Controller
{
    public $URL_FRONTEND;

    public function __construct()
    {
        $this->URL_FRONTEND = config('app.frontend_url');
    }
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
                'message' => 'Retrieve owned tags successfully',
                'data'    => $ownedTags->isEmpty() ? [] : $ownedTags
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving owned tags',
            ], 500);
        }
    }

    /**
     * Lấy danh sách tag được chia sẻ với người dùng.
     */
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
            $tag = Tag::find($id);
    
            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found',
                ], 404);
            }
    
            $userId = Auth::id();
    
            $sharedUsers = collect($tag->shared_user ?? []);
    
            $isInvited = $sharedUsers->firstWhere('user_id', $userId);
    
            if ($tag->user_id !== $userId && (!$isInvited || $isInvited['status'] !== 'yes')) {
                return response()->json([
                    'code'    => 403,
                    'message' => 'You are not invited to this tag',
                ], 403);
            }
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag invite details retrieved successfully',
                'data'    => [
                    'tag'     => $tag,
                    'invited' => $isInvited,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
    
            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving the tag invite details',
            ], 500);
        }
    }      
   
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'color_code'  => 'nullable|string',
            'shared_user' => 'nullable',
        ]);

        try {
            $userId = Auth::id();

            if (Tag::where('user_id', $userId)->where('name', $validated['name'])->exists()) {
                return response()->json([
                    'code'    => 409,
                    'message' => 'You already have a tag with this name',
                ], 409);
            }

            // Xử lý linh hoạt kiểu array hoặc JSON
            if (!empty($validated['shared_user']) && is_string($validated['shared_user'])) {
                $shared_user = json_decode($validated['shared_user'], true) ?? [];
            } elseif (is_array($validated['shared_user'])) {
                $shared_user = $validated['shared_user'];
            } else {
                $shared_user = [];
            }

            $formattedSharedUsers = collect($shared_user)->map(function ($user) {
                return [
                    'user_id'    => $user['user_id'],
                    'first_name' => $user['first_name'] ?? null,
                    'last_name'  => $user['last_name'] ?? null,
                    'email'      => $user['email'] ?? null,
                    'avatar'     => $user['avatar'] ?? null,
                    'status'     => $user['status'] ?? 'pending',
                    'role'       => $user['role'] ?? 'viewer',
                ];
            })->toArray();

            $tag = Tag::create([
                'name'        => $validated['name'],
                'description' => $validated['description'],
                'color_code'  => $validated['color_code'],
                'user_id'     => Auth::id(),
                'shared_user' => $formattedSharedUsers,
            ]);

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
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'color_code'  => 'nullable|string',
            'shared_user' => 'nullable',
        ]);
    
        try {
            $userId = Auth::id();
            $tag = Tag::where('id', $id)->where('user_id', $userId)->first();
    
            if (!$tag) {
                return response()->json(['code' => 404, 'message' => 'Tag not found or unauthorized'], 404);
            }
    
            // Lấy danh sách shared_user cũ TRƯỚC KHI UPDATE
            $oldSharedUsers = collect($tag->shared_user ?? [])->pluck('user_id')->toArray();
    
            // Xử lý linh hoạt JSON hoặc Array
            if (!empty($validated['shared_user'])) {
                if (is_string($validated['shared_user'])) {
                    $decodedSharedUsers = json_decode($validated['shared_user'], true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedSharedUsers)) {
                        return response()->json(['code' => 400, 'message' => 'Invalid shared_user JSON'], 400);
                    }
                    $newSharedUsers = $decodedSharedUsers;
                } elseif (is_array($validated['shared_user'])) {
                    $newSharedUsers = $validated['shared_user'];
                } else {
                    $newSharedUsers = [];
                }
            } else {
                $newSharedUsers = [];
            }
    
            // Lọc và định dạng shared_user mới
            $formattedSharedUsers = collect($newSharedUsers)->map(function ($user) {
                $userModel = User::find($user['user_id']);
                if (!$userModel) return null;
    
                return [
                    'user_id'    => $user['user_id'],
                    'first_name' => $userModel->first_name,
                    'last_name'  => $userModel->last_name,
                    'email'      => $userModel->email,
                    'avatar'     => $userModel->avatar,
                    'status'     => $user['status'] ?? 'pending',
                    'role'       => $user['role'] ?? 'viewer',
                ];
            })->filter()->values()->toArray();
    
            // Lấy danh sách người dùng cũ trước khi cập nhật
            $oldSharedUsers = collect($tag->shared_user ?? [])->pluck('user_id')->toArray();
    
            // Cập nhật tag
            $tag->update([
                'name'        => $validated['name'],
                'description' => $validated['description'],
                'color_code'  => $validated['color_code'],
                'shared_user' => $formattedSharedUsers,
            ]);
    
            // Xác định người dùng mới được thêm vào
            $addedUsers = array_diff(
                collect($formattedSharedUsers)->pluck('user_id')->toArray(),
                $oldSharedUsers
            );
    
            // Gửi mail và notification cho người dùng mới
            if (!empty($addedUsers)) {
                $emails = User::whereIn('id', $addedUsers)->pluck('email');
    
                if ($emails->isNotEmpty()) {
                    $this->sendMail(Auth::user()->email, $emails, $tag);
                }
    
                foreach ($addedUsers as $userId) {
                    $user = User::find($userId);
                    if ($user) {
                        $user->notify(new NotificationEvent(
                            $user->id,
                            "Bạn đã được mời tham gia tag: {$tag->name}",
                            "{$this->URL_FRONTEND}/tag/invite/{$tag->id}",
                            "invite_to_tag"
                        ));
                    }
                }
            }
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag updated successfully',
                'data'    => $tag,
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('Error updating tag:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while updating tag',
                'error_details' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
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
    
    public function acceptTagInvite($id)
    {
        $userId = Auth::id();
        $tag = Tag::find($id);
    
        if (!$tag) {
            return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
        }
    
        $sharedUsers = collect($tag->shared_user ?? [])
            ->map(function ($user) use ($userId) {
                if ($user['user_id'] == $userId) {
                    $user['status'] = 'yes';
                }
                return $user;
            })->toArray();
    
        $tag->update(['shared_user' => $sharedUsers]);
    
        // Gửi thông báo cho chủ sở hữu
        $owner = User::find($tag->user_id);
        if ($owner) {
            $owner->notify(new NotificationEvent(
                $owner->id,
                "Người dùng {$userId} đã chấp nhận lời mời vào tag: {$tag->name}",
                "",
                "accept_tag_invite"
            ));
        }
    
        return response()->json([
            'code' => 200,
            'message' => 'Successfully accepted tag invitation',
        ], 200);
    }
    

    public function declineTagInvite($id)
    {
        $userId = Auth::id();
        $tag = Tag::find($id);
    
        if (!$tag) {
            return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
        }
    
        $sharedUsers = collect($tag->shared_user ?? []);
    
        $newSharedUsers = $sharedUsers
            ->reject(fn($user) => $user['user_id'] == $userId)
            ->values()
            ->toArray();
    
        $tag->update(['shared_user' => $newSharedUsers]);
    
        // Gửi thông báo cho chủ sở hữu
        $owner = User::find($tag->user_id);
        if ($owner) {
            $owner->notify(new NotificationEvent(
                $owner->id,
                "Người dùng {$userId} đã từ chối lời mời vào tag: {$tag->name}",
                "",
                "decline_tag_invite"
            ));
        }
    
        return response()->json([
            'code' => 200,
            'message' => 'Successfully declined tag invitation',
        ], 200);
    }    

    protected function sendMail($mailOwner, $emails, $tag)
    {
        $nameOwner = Auth::user()->name;
        foreach ($emails as $email) {
            Mail::to($email)->queue(new InviteToTagMail($mailOwner, $nameOwner, $tag));
        }
    }
}