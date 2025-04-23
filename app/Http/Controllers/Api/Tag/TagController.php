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
    
            $sharedTags = Tag::whereJsonContains('shared_user', [[ 'user_id' => $userId ]])->get();
    
            $sharedTags = $sharedTags->map(function ($tag) {
                $owner = User::find($tag->user_id);
                return array_merge($tag->toArray(), [
                    'owner' => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null
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
    
            $ownedTags = Tag::where('user_id', $userId)->get();
    
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
                ->map(function ($tag) {
                    $owner = User::find($tag->user_id);
                    return array_merge($tag->toArray(), [
                        'owner' => $owner ? [
                            'user_id'    => $owner->id,
                            'first_name' => $owner->first_name,
                            'last_name'  => $owner->last_name,
                            'email'      => $owner->email,
                            'avatar'     => $owner->avatar,
                        ] : null
                    ]);
                })
                ->values();
    
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieved owned and shared-editor tags successfully',
                'data'    => [
                    'owned'            => $ownedTags,
                    'shared_as_editor' => $sharedEditorTags
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
    
            $owner = User::find($tag->user_id);
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag retrieved successfully',
                'data'    => [
                    'tag'         => $tag,
                    'invite_link' => "{$this->URL_FRONTEND}/calendar/tag/invite/{$tag->uuid}",
                    'owner'       => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null
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
                    'invite_link' => "{$this->URL_FRONTEND}/calendar/tag/invite/{$tag->uuid}",
                    'owner'       => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar,
                    ] : null
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
                        "{$this->URL_FRONTEND}/calendar/tag/invite/{$tag->uuid}",
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
    
            $tag->syncAttendeesWithTasks($oldSharedUsers);
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
            $tag = Tag::where('id', $id)->where('user_id', Auth::id())->first();
    
            if (!$tag) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Tag not found or unauthorized',
                ], 404);
            }
    
            $totalTags = Tag::where('user_id', Auth::id())->count();
    
            if ($totalTags <= 1) {
                return response()->json([
                    'code'    => 403,
                    'message' => 'Cannot delete the last remaining tag',
                ], 403);
            }
    
            $returnTag[] = clone $tag;
            $tasksForRealtime = [];
            $attendees = [];
    
            foreach ($tag->tasks as $task) {
                $tasksForRealtime[] = clone $task;
    
                // Merge attendees (JSON) và users (quan hệ many-to-many)
                $taskAttendees = collect($task->attendees ?? []);
                $taskUsers     = collect($task->users ?? [])->map(function ($user) {
                    return ['user_id' => $user->id];
                });
    
                $merged = $taskAttendees->merge($taskUsers)->toArray();
                $attendees = array_merge($attendees, $merged);
    
                $task->forceDelete();
            }
    
            // Lọc tên các task bị xóa
            $taskTitles = collect($tasksForRealtime)->pluck('title')->filter()->values()->toArray();
            $taskTitlesStr = implode(', ', $taskTitles);
    
            // Loại bỏ trùng lặp người tham gia
            $uniqueAttendees = collect($attendees)->unique('user_id')->values();
    
            $is_send_mail = 'yes';
    
            foreach ($uniqueAttendees as $attendee) {
                if ($attendee['user_id'] != Auth::id()) {
                    $user = User::find($attendee['user_id']);
                    if ($user) {
                        Log::info("Sending notification to user: {$user->id}");
    
                        // Gửi notification kèm tên task bị xóa
                        $user->notify(new NotificationEvent(
                            $user->id,
                            "Các task sau đã bị xóa: {$taskTitlesStr}",
                            "",
                            "delete_tag_tasks"
                        ));
    
                        // Gửi email nếu bật
                        if ($is_send_mail === 'yes') {
                            Mail::to($user->email)->queue(new SendNotificationMail($user, $tag, 'delete'));
                        }
                    }
                }
            }
    
            $tag->delete();
    
            // Gửi realtime cho task và tag
            $this->sendRealTimeUpdateTasks($tasksForRealtime, 'delete');
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

        $tag->syncAttendeesWithTasks($sharedUsers->pluck('user_id')->toArray());
    
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

    public function removeUserFromTag(Request $request, $tagId, $userIdToRemove)
    {
        try {
            $userId = Auth::id();
            $tag = Tag::find($tagId);

            if (!$tag) {
                return response()->json(['code' => 404, 'message' => 'Tag not found'], 404);
            }

            // Chỉ chủ sở hữu tag mới có quyền
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

            // Xoá người dùng ra khỏi danh sách shared_user
            $newSharedUsers = $sharedUsers
                ->reject(fn($user) => $user['user_id'] == $userIdToRemove)
                ->values()
                ->toArray();

            $tag->update(['shared_user' => $newSharedUsers]);

            $tag->syncAttendeesWithTasks($sharedUsers->pluck('user_id')->toArray());

            // Gửi thông báo (tuỳ chọn)
            $removedUser = User::find($userIdToRemove);
            if ($removedUser) {
                $removedUser->notify(new NotificationEvent(
                    $removedUser->id,
                    "Bạn đã bị xóa khỏi tag: {$tag->name}",
                    "",
                    "removed_from_tag"
                ));
            }

            return response()->json([
                'code' => 200,
                'message' => 'User removed from tag successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while removing user from tag',
            ], 500);
        }
    }

    protected function sendMail($mailOwner, $emails, $tag)
    {
        $nameOwner = Auth::user()->name;
        foreach ($emails as $email) {
            Mail::to($email)->queue(new InviteToTagMail($mailOwner, $nameOwner, $tag));
        }
    }
}