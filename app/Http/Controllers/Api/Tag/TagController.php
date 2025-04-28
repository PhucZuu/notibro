<?php

namespace App\Http\Controllers\Api\Tag;

use App\Events\Tag\TagUpdatedEvetn;
use App\Events\Task\TaskUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Mail\InviteToTagMail;
use App\Mail\SendNotificationMail;
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

    public function getRecipientsTasks($data)
    {
        $recipientsTasks = collect($data)
            ->flatMap(function ($task) {
                $attendees = method_exists($task, 'getAttendeesForRealTime') ? $task->getAttendeesForRealTime() : [];
                return $attendees;
            })
            ->unique()
            ->values()
            ->toArray();
    
    
        return $recipientsTasks;
    }
    

    public function sendRealTimeUpdateTasks($data, $action)
    {
        $recipientsTasks = $this->getRecipientsTasks($data);

        event(new TaskUpdatedEvent($data, $action, $recipientsTasks));
    }

    public function index()
    {
        try {
            $userId = Auth::id();
    
            $ownedTags = Tag::where('user_id', $userId)->get();
    
            $tagsWithOwnerInfo = $ownedTags->map(function ($tag) use ($userId) {
                $owner = User::find($tag->user_id);
                return array_merge($tag->toArray(), [
                    'owner' => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null,
                    'is_owner' => true,
                ]);
            });
    
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve owned tags successfully',
                'data'    => $tagsWithOwnerInfo->isEmpty() ? [] : $tagsWithOwnerInfo
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
    
            $sharedTags = Tag::whereJsonContains('shared_user', [['user_id' => $userId]])
                ->get()
                ->filter(function ($tag) use ($userId) {
                    return collect($tag->shared_user)
                        ->contains(function ($user) use ($userId) {
                            return (int) $user['user_id'] === $userId && $user['status'] === 'yes';
                        });
                })
                ->map(function ($tag) use ($userId) {
                    $owner = User::find($tag->user_id);
                    return array_merge($tag->toArray(), [
                        'owner' => $owner ? [
                            'user_id'    => $owner->id,
                            'first_name' => $owner->first_name,
                            'last_name'  => $owner->last_name,
                            'email'      => $owner->email,
                            'avatar'     => $owner->avatar,
                        ] : null,
                        'is_owner' => $tag->user_id === $userId,
                    ]);
                });
    
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
    
    public function getMyAndSharedEditorTags()
    {
        try {
            $userId = Auth::id();
    
            // 1. Các tag do chính mình sở hữu (owned)
            $ownedTags = Tag::where('user_id', $userId)
                ->get()
                ->map(function ($tag) use ($userId) {
                    $owner = User::find($tag->user_id);
                    return array_merge($tag->toArray(), [
                        'owner' => $owner ? [
                            'user_id'    => $owner->id,
                            'first_name' => $owner->first_name,
                            'last_name'  => $owner->last_name,
                            'email'      => $owner->email,
                            'avatar'     => $owner->avatar,
                        ] : null,
                        'is_owner' => true,
                    ]);
                });
    
            // 2. Các tag được chia sẻ với mình dưới vai trò editor
            $sharedEditorTags = Tag::whereJsonContains('shared_user', [['user_id' => $userId]])
                ->get()
                ->filter(function ($tag) use ($userId) {
                    return collect($tag->shared_user)
                        ->contains(function ($user) use ($userId) {
                            return (int) $user['user_id'] === $userId
                                && $user['role'] === 'editor'
                                && $user['status'] === 'yes';
                        });
                })
                ->map(function ($tag) use ($userId) {
                    $owner = User::find($tag->user_id);
                    return array_merge($tag->toArray(), [
                        'owner' => $owner ? [
                            'user_id'    => $owner->id,
                            'first_name' => $owner->first_name,
                            'last_name'  => $owner->last_name,
                            'email'      => $owner->email,
                            'avatar'     => $owner->avatar,
                        ] : null,
                        'is_owner' => false,
                    ]);
                })
                ->values();
    
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieved owned and shared-editor tags successfully',
                'data'    => [
                    'owned'            => $ownedTags,
                    'shared_as_editor' => $sharedEditorTags,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
    
            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving tags',
            ], 500);
        }
    }    

    public function getTasksFromSharedTags()
    {
        try {
            $userId = Auth::id();

            // Lấy tất cả tag mà user là shared_user (status = yes)
            $sharedTags = Tag::whereJsonContains('shared_user', [['user_id' => $userId]])
                ->get()
                ->filter(function ($tag) use ($userId) {
                    return collect($tag->shared_user)
                        ->contains(function ($user) use ($userId) {
                            return (int) $user['user_id'] === $userId && $user['status'] === 'yes';
                        });
                });

            // Lấy tất cả task từ các tag đó
            $tasks = $sharedTags->flatMap(function ($tag) {
                return $tag->tasks;
            })->values();

            return response()->json([
                'code'    => 200,
                'message' => 'Retrieved tasks from shared tags successfully',
                'data'    => $tasks,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred while retrieving tasks',
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
    
            $user = Auth::user();
    
            // Nếu không đăng nhập hoặc không có quyền xem
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'message' => 'You must be logged in to view this invitation',
                ], 401);
            }
    
            $userId = $user->id;
            $isOwner = $tag->user_id === $userId;
    
            $isInvited = collect($tag->shared_user ?? [])
                ->firstWhere('user_id', $userId);
    
            if (!$isOwner && (!$isInvited || !in_array($isInvited['status'], ['pending', 'yes']))) {
                return response()->json([
                    'code' => 403,
                    'message' => 'You are not allowed to view this invitation',
                ], 403);
            }
    
            $owner = User::find($tag->user_id);
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag retrieved successfully',
                'data'    => [
                    'tag'         => $tag,
                    'invite_link' => "{$this->URL_FRONTEND}/calendar/tag/{$tag->uuid}/invite",
                    'owner'       => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null,
                    'is_owner'    => $isOwner,
                ],
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
    
            $owner = User::find($tag->user_id);
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag details retrieved successfully',
                'data'    => [
                    'tag'         => $tag,
                    'invited'     => $isInvited,
                    'invite_link' => "{$this->URL_FRONTEND}/calendar/tag/{$tag->uuid}/invite",
                    'owner'       => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null,
                    'is_owner' => $tag->user_id === $userId,
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
    
            $emails = collect($formattedSharedUsers)->pluck('email')->filter();
            if ($emails->isNotEmpty()) {
                $this->sendMail(Auth::user()->email, $emails, $tag);
            }
    
            foreach ($formattedSharedUsers as $user) {
                $userModel = User::find($user['user_id']);
                if ($userModel) {
                    $userModel->notify(new NotificationEvent(
                        $userModel->id,
                        "Bạn đã được mời tham gia tag: {$tag->name}",
                        "{$this->URL_FRONTEND}/calendar/tag/{$tag->uuid}/invite",
                        "invite_to_tag"
                    ));
                }
            }
    
            $owner = User::find($userId);
            return response()->json([
                'code'    => 201,
                'message' => 'Tag created successfully',
                'data'    => [
                    'tag'   => $tag,
                    'owner' => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null
                ]
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
            $tag = Tag::find($id);
    
            if (!$tag) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Tag not found'
                ], 404);
            }
    
            $isOwner = $tag->user_id === $userId;
            $isEditor = collect($tag->shared_user ?? [])->contains(fn($user) => $user['user_id'] == $userId && $user['role'] === 'editor');
    
            if (!($isOwner || $isEditor)) {
                return response()->json([
                    'code' => 403,
                    'message' => 'You do not have permission to update this tag',
                ], 403);
            }
    
            $oldSharedUsers = collect($tag->shared_user ?? [])->pluck('user_id')->toArray();
            $formattedSharedUsers = $tag->shared_user;
    
            if (($isOwner || $isEditor) && isset($validated['shared_user'])) {
                $sharedUsersRaw = is_string($validated['shared_user']) ? json_decode($validated['shared_user'], true) : $validated['shared_user'];
    
                if (!is_array($sharedUsersRaw)) {
                    return response()->json(['code' => 400, 'message' => 'Invalid shared_user JSON'], 400);
                }
    
                $existingUsers = collect($tag->shared_user ?? []);
    
                if ($isOwner) {
                    $formattedSharedUsers = collect($sharedUsersRaw)->map(function ($user) {
                        $userModel = User::find($user['user_id']);
                        if (!$userModel) return null;
    
                        return [
                            'user_id'    => $userModel->id,
                            'first_name' => $userModel->first_name,
                            'last_name'  => $userModel->last_name,
                            'email'      => $userModel->email,
                            'avatar'     => $userModel->avatar,
                            'status'     => $user['status'] ?? 'pending',
                            'role'       => $user['role'] ?? 'viewer',
                        ];
                    })->filter()->values()->toArray();
                } else {
                    $newUsers = collect($sharedUsersRaw)->filter(fn($user) => !$existingUsers->contains('user_id', $user['user_id']));
    
                    $newFormattedUsers = $newUsers->map(function ($user) {
                        $userModel = User::find($user['user_id']);
                        if (!$userModel) return null;
    
                        return [
                            'user_id'    => $userModel->id,
                            'first_name' => $userModel->first_name,
                            'last_name'  => $userModel->last_name,
                            'email'      => $userModel->email,
                            'avatar'     => $userModel->avatar,
                            'status'     => 'pending',
                            'role'       => 'viewer',
                        ];
                    })->filter()->values();
    
                    $formattedSharedUsers = $existingUsers->merge($newFormattedUsers)->values()->toArray();
                }
            }
    
            $reminderRaw = $validated['reminder'] ?? [];
            if (is_string($reminderRaw)) {
                $reminderRaw = json_decode($reminderRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($reminderRaw)) {
                    return response()->json(['code' => 400, 'message' => 'Invalid reminder JSON'], 400);
                }
            }
    
            $formattedReminder = collect($reminderRaw)->map(fn($item) => [
                'type'   => $item['type'] ?? null,
                'time'   => $item['time'] ?? null,
                'method' => $item['method'] ?? 'once',
            ])->filter(fn($r) => $r['type'] && $r['time'])->values()->toArray();
    
            $tag->update([
                'name'         => $validated['name'],
                'description'  => $validated['description'],
                'color_code'   => $validated['color_code'],
                'shared_user'  => $formattedSharedUsers,
                'reminder'     => $formattedReminder,
            ]);
    
            // $tag->syncAttendeesWithTasks($oldSharedUsers);
            $returnTag[] = $tag;
            $this->sendRealTimeUpdate($returnTag, 'update');
    
            $owner = User::find($tag->user_id);
            return response()->json([
                'code'    => 200,
                'message' => 'Tag updated successfully',
                'data'    => [
                    'tag'   => $tag,
                    'owner' => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null
                ],
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
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $userId = Auth::id();
            $tag = Tag::where('id', $id)->where('user_id', $userId)->first();
    
            if (!$tag) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Tag not found or unauthorized',
                ], 404);
            }
    
            $totalTags = Tag::where('user_id', $userId)->count();
            if ($totalTags <= 1) {
                return response()->json([
                    'code' => 403,
                    'message' => 'Cannot delete the last remaining tag',
                ], 403);
            }
    
            $returnTag[] = clone $tag;
            $tasksForRealtime = [];
            $userTaskMap = []; // user_id => [task titles]
              
            foreach ($tag->tasks as $task) {
                $tasksForRealtime[] = clone $task;

                $attendees = collect($task->attendees ?? []);
                $users = collect($task->users ?? [])->map(fn($user) => ['user_id' => $user->id]);
                $allAttendees = $attendees->merge($users)->unique('user_id');

                foreach ($allAttendees as $attendee) {
                    $uid = $attendee['user_id'];
                    if (!isset($userTaskMap[$uid])) {
                        $userTaskMap[$uid] = [];
                    }
                    $userTaskMap[$uid][] = $task->title ?? '';
                }
            }

            $this->sendRealTimeUpdateTasks($tasksForRealtime, 'delete');

            foreach ($tag->tasks as $task) {
                $task->forceDelete();
            }

            $tagName = $tag->name ?? 'Unnamed Tag';
            $tag->delete();


            $this->sendRealTimeUpdate($returnTag, 'delete');
    
    
            // Gửi thông báo cho người liên quan
            foreach ($userTaskMap as $uid => $tasks) {
                if ($uid != $userId) { 
                    $user = User::find($uid);
                    if ($user) {
                        $taskList = implode(', ', $tasks);
                        $message = "Tag '{$tagName}' đã bị xóa, các task liên quan: {$taskList}.";
    
                        $user->notify(new NotificationEvent(
                            $user->id,
                            $message,
                            "",
                            "delete_tag_tasks"
                        ));
                    }
                }
            }
    
            return response()->json([
                'code' => 200,
                'message' => 'Tag and related tasks deleted successfully',
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('Error deleting tag:', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
    
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while deleting tag and tasks',
            ], 500);
        }
    }
    
    public function acceptTagInvite($uuid)
    {
        $userId = Auth::id();
        $tag = Tag::where('uuid', $uuid)->first();
        $fullName = Auth::user()->first_name . ' ' . Auth::user()->last_name;
    
        if (!$tag || !$tag->shared_user) {
            return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
        }
    
        $sharedUsers = collect($tag->shared_user);
        $isInvited = $sharedUsers->firstWhere('user_id', $userId);
    
        if (!$isInvited) {
            return response()->json(['code' => 403, 'message' => 'You are not invited to this tag'], 403);
        }
    
        $updatedSharedUsers = $sharedUsers->map(function ($user) use ($userId) {
            if ($user['user_id'] == $userId) {
                $user['status'] = 'yes';
            }
            return $user;
        })->toArray();
    
        $tag->update(['shared_user' => $updatedSharedUsers]);
    
        $user = Auth::user();
        $owner = User::find($tag->user_id);
        if ($owner) {
            $owner->notify(new NotificationEvent(
                $owner->id,
                "{$fullName} đã chấp nhận lời mời vào tag: {$tag->name}",
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
        $fullName = Auth::user()->first_name . ' ' . Auth::user()->last_name;
    
        if (!$tag) {
            return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
        }
    
        $sharedUsers = collect($tag->shared_user ?? []);
    
        $newSharedUsers = $sharedUsers
            ->reject(fn($user) => $user['user_id'] == $userId)
            ->values()
            ->toArray();
    
        $tag->update(['shared_user' => $newSharedUsers]);
    
        $owner = User::find($tag->user_id);
        $currentUser = Auth::user();
        if ($owner && $currentUser) {
            $owner->notify(new NotificationEvent(
                $owner->id,
                "{$fullName} đã từ chối lời mời vào tag: {$tag->name}",
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
        try {
            $userId = Auth::id();
            $currentUser = Auth::user();
            $tag = Tag::find($id);
    
            if (!$tag) {
                return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
            }
    
            if ($tag->user_id == $userId) {
                return response()->json(['code' => 403, 'message' => 'You are the owner of this tag and cannot leave'], 403);
            }
    
            $sharedUsers = collect($tag->shared_user ?? []);
    
            if (!$sharedUsers->firstWhere('user_id', $userId)) {
                return response()->json(['code' => 403, 'message' => 'You are not part of this tag'], 403);
            }
    
            // 1. Xóa user khỏi shared_user
            $newSharedUsers = $sharedUsers
                ->reject(fn($user) => $user['user_id'] == $userId)
                ->values()
                ->toArray();
            $tag->update(['shared_user' => $newSharedUsers]);
    
            // 2. Xóa user khỏi attendees của các task mà họ thực sự tham gia
            $tasksLeft = [];
            foreach ($tag->tasks as $task) {
                $attendees = collect($task->attendees ?? []);
                if ($attendees->contains('user_id', $userId)) {
                    $updatedAttendees = $attendees
                        ->reject(fn($attendee) => $attendee['user_id'] == $userId)
                        ->values()
                        ->toArray();
                    $task->update(['attendees' => $updatedAttendees]);
                    $tasksLeft[] = $task;
                }
            }
    
            // 3. Gửi thông báo cho chủ sở hữu
            $owner = User::find($tag->user_id);
            if ($owner) {
                $fullName = trim(($currentUser->first_name ?? '') . ' ' . ($currentUser->last_name ?? ''));
    
                $taskNames = collect($tasksLeft)->pluck('title')->filter()->values()->toArray();
                $taskNamesStr = !empty($taskNames) ? ' và các task: ' . implode(', ', $taskNames) : '';
    
                $owner->notify(new NotificationEvent(
                    $owner->id,
                    "{$fullName} đã rời khỏi tag: {$tag->name} và các task liên quan: {$taskNamesStr}",
                    "",
                    "leave_tag"
                ));
            }
    
            // 4. Gửi realtime
            if (!empty($tasksLeft)) {
                $this->sendRealTimeUpdateTasks($tasksLeft, 'update');
            }
            $this->sendRealTimeUpdate([$tag], 'update');
    
            return response()->json([
                'code' => 200,
                'message' => 'Successfully left the tag and removed from related tasks',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
    
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while leaving the tag',
            ], 500);
        }
    }

    public function removeUserFromTag(Request $request, $tagId, $userIdToRemove)
    {
        try {
            $userId = Auth::id();
            $keepInTasks = $request->input('keep_in_tasks', false);
            $tag = Tag::find($tagId);
    
            if (!$tag) {
                return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
            }
    
            if ($tag->user_id !== $userId) {
                return response()->json([
                    'code' => 403,
                    'message' => 'Only the tag owner can remove users',
                ], 403);
            }
    
            $sharedUsers = collect($tag->shared_user ?? []);
    
            if (!$sharedUsers->contains('user_id', $userIdToRemove)) {
                return response()->json([
                    'code' => 404,
                    'message' => 'User not found in shared_user list',
                ], 404);
            }
    
            // Xoá user khỏi shared_user
            $newSharedUsers = $sharedUsers
                ->reject(fn($user) => $user['user_id'] == $userIdToRemove)
                ->values()
                ->toArray();
    
            $tag->update(['shared_user' => $newSharedUsers]);
    
            $tasksUpdated = [];
    
            if (!$keepInTasks) {
                // Nếu yêu cầu xóa user khỏi cả task
                foreach ($tag->tasks as $task) {
                    $attendees = collect($task->attendees ?? [])
                        ->reject(fn($attendee) => $attendee['user_id'] == $userIdToRemove)
                        ->values()
                        ->toArray();
    
                    $task->update(['attendees' => $attendees]);
    
                    $tasksUpdated[] = $task;
                }
            }
    
            $removedUser = User::find($userIdToRemove);
    
            if ($removedUser) {
                $taskNames = collect($tasksUpdated)->pluck('title')->filter()->values()->toArray();
                $taskListStr = implode(', ', $taskNames);
    
                $message = $keepInTasks
                    ? "Bạn đã bị xóa khỏi tag: {$tag->name}"
                    : (empty($taskListStr)
                        ? "Bạn đã bị xóa khỏi tag: {$tag->name}"
                        : "Bạn đã bị xóa khỏi tag: {$tag->name} và khỏi các task: {$taskListStr}");
    
                $removedUser->notify(new NotificationEvent(
                    $removedUser->id,
                    $message,
                    "",
                    "removed_from_tag"
                ));
            }
    
            // Gửi realtime update cho tag
            $this->sendRealTimeUpdate([$tag], 'update');
    
            // Gửi realtime update cho các task bị chỉnh sửa attendees
            if (!empty($tasksUpdated)) {
                $this->sendRealTimeUpdateTasks($tasksUpdated, 'update');
            }
    
            return response()->json([
                'code' => 200,
                'message' => 'User removed from tag successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error removing user from tag: ' . $e->getMessage());
    
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while removing user from tag',
            ], 500);
        }
    }
    

    protected function sendMail($mailOwner, $emails, $tag)
    {
        $user = Auth::user();
        $nameOwner = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        foreach ($emails as $email) {
            Mail::to($email)->queue(new InviteToTagMail($mailOwner, $nameOwner, $tag));
        }
    }
}