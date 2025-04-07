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
    
    public function show($uuid)
    {
        try {
            $tag = Tag::where('uuid', $uuid)->first();
    
            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found',
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

    public function showOne($id)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'code'    => 401,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $userId = Auth::id();
            $tag = Tag::find($id);

            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found',
                ], 404);
            }

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
                'message' => 'Tag details retrieved successfully',
                'data'    => [
                    'tag'     => $tag,
                    'invited' => $isInvited,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving the tag details',
            ], 500);
        }
    }

   
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string',
            'description'  => 'nullable|string',
            'color_code'   => 'nullable|string',
            'reminder'     => 'nullable',
            'shared_user'  => 'nullable',
        ]);
    
        try {
            $userId = Auth::id();
    
            if (Tag::where('user_id', $userId)->where('name', $validated['name'])->exists()) {
                return response()->json([
                    'code'    => 409,
                    'message' => 'You already have a tag with this name',
                ], 409);
            }
    
            $sharedUsersRaw = $validated['shared_user'] ?? [];
            if (is_string($sharedUsersRaw)) {
                $sharedUsersRaw = json_decode($sharedUsersRaw, true) ?? [];
            }
    
            $formattedSharedUsers = collect($sharedUsersRaw)->map(function ($user) {
                return [
                    'user_id'    => $user['user_id'],
                    'first_name' => $user['first_name'] ?? null,
                    'last_name'  => $user['last_name'] ?? null,
                    'email'      => $user['email'] ?? null,
                    'avatar'     => $user['avatar'] ?? null,
                    'status'     => $user['status'] ?? 'pending',
                    'role'       => $user['role'] ?? 'viewer',
                ];
            })->filter()->values()->toArray();
    
            $reminderRaw = $validated['reminder'] ?? [];
            if (is_string($reminderRaw)) {
                $reminderRaw = json_decode($reminderRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($reminderRaw)) {
                    return response()->json(['code' => 400, 'message' => 'Invalid reminder JSON'], 400);
                }
            }
    
            $formattedReminder = collect($reminderRaw)->map(function ($item) {
                return [
                    'type'   => $item['type']   ?? null,
                    'time'   => $item['time']   ?? null,
                    'method' => $item['method'] ?? 'once',
                ];
            })->filter(fn($r) => $r['type'] && $r['time'])->values()->toArray();
    
            $tag = Tag::create([
                'name'         => $validated['name'],
                'description'  => $validated['description'],
                'color_code'   => $validated['color_code'],
                'user_id'      => $userId,
                'shared_user'  => $formattedSharedUsers,
                'reminder'     => $formattedReminder,
            ]);
    
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
            'reminder'     => 'nullable',
            'shared_user'  => 'nullable',
        ]);
    
        try {
            $userId = Auth::id();
            $tag = Tag::where('id', $id)->where('user_id', $userId)->first();
    
            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found or unauthorized'
                ], 404);
            }
    
            $oldSharedUsers = collect($tag->shared_user ?? [])->pluck('user_id')->toArray();
    
            $sharedUsersRaw = $validated['shared_user'] ?? [];
            if (is_string($sharedUsersRaw)) {
                $sharedUsersRaw = json_decode($sharedUsersRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($sharedUsersRaw)) {
                    return response()->json(['code' => 400, 'message' => 'Invalid shared_user JSON'], 400);
                }
            }
    
            $formattedSharedUsers = collect($sharedUsersRaw)->map(function ($user) {
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
    
            // ✅ Parse & format reminder
            $reminderRaw = $validated['reminder'] ?? [];
            if (is_string($reminderRaw)) {
                $reminderRaw = json_decode($reminderRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($reminderRaw)) {
                    return response()->json(['code' => 400, 'message' => 'Invalid reminder JSON'], 400);
                }
            }
    
            $formattedReminder = collect($reminderRaw)->map(function ($item) {
                return [
                    'type'   => $item['type']   ?? null,
                    'time'   => $item['time']   ?? null,
                    'method' => $item['method'] ?? 'once',
                ];
            })->filter(fn($r) => $r['type'] && $r['time'])->values()->toArray();
    
            $tag->update([
                'name'         => $validated['name'],
                'description'  => $validated['description'],
                'color_code'   => $validated['color_code'],
                'shared_user'  => $formattedSharedUsers,
                'reminder'     => $formattedReminder,
            ]);
    
            $tag->syncAttendeesWithTasks($oldSharedUsers);
    
            $returnTag[] = $tag;

            $this->sendRealTimeUpdate($returnTag, 'update');

            $newUserIds = collect($formattedSharedUsers)->pluck('user_id')->toArray();
            $addedUsers = array_diff($newUserIds, $oldSharedUsers);
    
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
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
    
            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while updating tag',
                'error_details' => [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
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
    
            // Đếm tổng số tag của user hiện tại
            $totalTags = Tag::where('user_id', Auth::id())->count();
    
            if ($totalTags <= 1) {
                return response()->json([
                    'code'    => 403,
                    'message' => 'Cannot delete the last remaining tag',
                ], 403);
            }
    
            $returnTag[] = clone $tag;
    
            foreach ($tag->tasks as $task) {
                $task->delete();
            }
    
            $tag->delete();
    
            $this->sendRealTimeUpdate($returnTag, 'delete');
    
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
    
    
    
    public function acceptTagInvite($uuid)
    {
        $userId = Auth::id();
        $tag = Tag::where('uuid', $uuid)->first();
    
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
    
    public function declineTagInvite($uuid)
    {
        $userId = Auth::id();
        $tag = Tag::where('uuid', $uuid)->first();
    
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
      
    public function leaveTag($id)
    {
        $userId = Auth::id();
        $tag = Tag::find($id);
    
        if (!$tag) {
            return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
        }
    
        // Ngăn chủ sở hữu tag rời đi
        if ($tag->user_id == $userId) {
            return response()->json(['code' => 403, 'message' => 'You are the owner of this tag and cannot leave'], 403);
        }
    
        $sharedUsers = collect($tag->shared_user ?? []);
    
        // Kiểm tra nếu người dùng có trong danh sách shared_user
        if (!$sharedUsers->firstWhere('user_id', $userId)) {
            return response()->json(['code' => 403, 'message' => 'You are not part of this tag'], 403);
        }
    
        // Loại bỏ user khỏi danh sách shared_user
        $newSharedUsers = $sharedUsers
            ->reject(fn($user) => $user['user_id'] == $userId)
            ->values()
            ->toArray();
    
        $tag->update(['shared_user' => $newSharedUsers]);
    
        // Loại bỏ user khỏi tất cả các task của tag
        $tasks = $tag->tasks;
        foreach ($tasks as $task) {
            $attendees = collect($task->attendees ?? [])
                ->reject(fn($attendee) => $attendee['user_id'] == $userId)
                ->values()
                ->toArray();
    
            $task->update(['attendees' => $attendees]);
        }
    
        // Gửi thông báo cho chủ sở hữu
        $owner = User::find($tag->user_id);
        if ($owner) {
            $owner->notify(new NotificationEvent(
                $owner->id,
                "Người dùng {$userId} đã rời khỏi tag: {$tag->name}",
                "",
                "leave_tag"
            ));
        }
    
        return response()->json([
            'code' => 200,
            'message' => 'Successfully left the tag and related tasks',
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