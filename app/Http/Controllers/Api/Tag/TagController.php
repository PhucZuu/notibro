<?php

namespace App\Http\Controllers\Api\Tag;

use App\Events\Tag\TagUpdatedEvetn;
use App\Events\Task\TaskUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Mail\InviteToTagMail;
use App\Mail\RemoveFromTagMail;
use App\Mail\SendNotificationMail;
use Illuminate\Http\Request;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NotificationEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public $URL_FRONTEND;

    public function __construct()
    {
        $this->URL_FRONTEND = config('app.frontend_url');
    }

    protected function formatSharedUsers($sharedUserArray)
    {
        return collect($sharedUserArray ?? [])->map(function ($shared) {
            $user = User::find($shared['user_id']);
            if ($user && $user->avatar && !Str::startsWith($user->avatar, ['http://', 'https://'])) {
                $user->avatar = Storage::url($user->avatar);
            }

            return [
                'user_id'    => $shared['user_id'],
                'first_name' => $user?->first_name,
                'last_name'  => $user?->last_name,
                'email'      => $user?->email ?? ($shared['email'] ?? null),
                'avatar'     => $user?->avatar,
                'status'     => $shared['status'] ?? 'pending',
                'role'       => $shared['role'] ?? 'viewer',
            ];
        })->values();
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
    

    public function sendRealTimeUpdateTasks($data, $action, $userIdsOverride = null)
    {
        $recipientsTasks = $userIdsOverride ?? $this->getRecipientsTasks($data);
    
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
                    'shared_user' => $this->formatSharedUsers($tag->shared_user),
                    'owner' => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $owner->avatar && !Str::startsWith($owner->avatar, ['http://', 'https://'])
                            ? Storage::url($owner->avatar)
                            : $owner->avatar,
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
                        'shared_user' => $this->formatSharedUsers($tag->shared_user),
                        'owner' => $owner ? [
                            'user_id'    => $owner->id,
                            'first_name' => $owner->first_name,
                            'last_name'  => $owner->last_name,
                            'email'      => $owner->email,
                            'avatar'     => $owner->avatar && !Str::startsWith($owner->avatar, ['http://', 'https://'])
                                ? Storage::url($owner->avatar)
                                : $owner->avatar,
                        ] : null,
                        'is_owner' => $tag->user_id === $userId,
                    ]);
                });
    
            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve shared tags successfully',
                'data'    => $sharedTags->isEmpty() ? [] : $sharedTags->values()->all() //đang trả về object -> thêm values() để reset lại chỉ số index
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
    
            // 1. Các tag do chính mình sở hữu
            $ownedTags = Tag::where('user_id', $userId)
                ->get()
                ->map(function ($tag) {
                    $owner = User::find($tag->user_id);
                    return array_merge($tag->toArray(), [
                        'shared_user' => $this->formatSharedUsers($tag->shared_user),
                        'owner' => $owner ? [
                            'user_id'    => $owner->id,
                            'first_name' => $owner->first_name,
                            'last_name'  => $owner->last_name,
                            'email'      => $owner->email,
                            'avatar'     => $owner->avatar && !Str::startsWith($owner->avatar, ['http://', 'https://'])
                                ? Storage::url($owner->avatar)
                                : $owner->avatar,
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
                        'shared_user' => $this->formatSharedUsers($tag->shared_user),
                        'owner' => $owner ? [
                            'user_id'    => $owner->id,
                            'first_name' => $owner->first_name,
                            'last_name'  => $owner->last_name,
                            'email'      => $owner->email,
                            'avatar'     => $owner->avatar && !Str::startsWith($owner->avatar, ['http://', 'https://'])
                                ? Storage::url($owner->avatar)
                                : $owner->avatar,
                        ] : null,
                        'is_owner' => false,
                    ]);
                })->values();
    
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
    
            // Sửa shared_user cho đúng
            $sharedUsers = collect($tag->shared_user ?? [])->map(function ($shared) {
                $user = User::find($shared['user_id']);

                if ($user->avatar && !Str::startsWith($user->avatar, ['http://', 'https://'])) {
                    $user->avatar = Storage::url($user->avatar);
                }

                return [
                    'user_id' => $shared['user_id'],
                    'first_name' => $user ? $user->first_name : null,
                    'last_name' => $user ? $user->last_name : null,
                    'email' => $user ? $user->email : ($shared['email'] ?? null),
                    'avatar' => $user->avatar,
                    'status' => $shared['status'] ?? 'pending',
                    'role' => $shared['role'] ?? 'viewer',
                ];
            })->values(); // <-- reset lại chỉ số index cho đúng
            Log::info('Shared User Data:', $sharedUsers->toArray());
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
                        'avatar'     => $owner->avatar && !Str::startsWith($owner->avatar, ['http://', 'https://'])
                            ? Storage::url($owner->avatar)
                            : $owner->avatar,
                    ] : null,
                    'shared_user' => $sharedUsers, // <-- thêm vào đúng định dạng
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
            $ownerAvatar = $owner && $owner->avatar && !Str::startsWith($owner->avatar, ['http://', 'https://'])
                ? Storage::url($owner->avatar)
                : ($owner->avatar ?? null);
    
            return response()->json([
                'code'    => 200,
                'message' => 'Tag details retrieved successfully',
                'data'    => [
                    'tag'         => array_merge($tag->toArray(), [
                        'shared_user' => $this->formatSharedUsers($tag->shared_user)
                    ]),
                    'invited'     => $isInvited,
                    'invite_link' => "{$this->URL_FRONTEND}/calendar/tag/{$tag->uuid}/invite",
                    'owner'       => $owner ? [
                        'user_id'    => $owner->id,
                        'first_name' => $owner->first_name,
                        'last_name'  => $owner->last_name,
                        'email'      => $owner->email,
                        'avatar'     => $ownerAvatar,
                    ] : null,
                    'is_owner'    => $tag->user_id === $userId,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in showOne:', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
    
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
                    'email'      => $user['email'] ?? null,
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
                        "Bạn đã được mời tham gia thẻ: {$tag->name}",
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
                    'tag'   => array_merge($tag->toArray(), [
                    'shared_user' => $this->formatSharedUsers($tag->shared_user)
                ]),
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
    
            // Chống trùng tên với tag của chủ sở hữu
            $ownerTagConflict = Tag::where('user_id', $tag->user_id)
                ->where('name', $validated['name'])
                ->where('id', '!=', $tag->id)
                ->exists();
    
            if ($ownerTagConflict) {
                return response()->json([
                    'code' => 409,
                    'message' => 'The owner already has a tag with this name',
                ], 409);
            }
    
            // Nếu không phải chủ sở hữu, không được đặt trùng tên tag chính mình đã có
            if (!$isOwner) {
                $userTagConflict = Tag::where('user_id', $userId)
                    ->where('name', $validated['name'])
                    ->where('id', '!=', $tag->id)
                    ->exists();
    
                if ($userTagConflict) {
                    return response()->json([
                        'code' => 409,
                        'message' => 'You already have a tag with this name',
                    ], 409);
                }
            }
    
            $oldSharedUserIds = collect($tag->shared_user ?? [])->pluck('user_id')->toArray();
            $formattedSharedUsers = $tag->shared_user;
    
            if (isset($validated['shared_user'])) {
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
                            'user_id' => $userModel->id,
                            'email'   => $userModel->email,
                            'status'  => $user['status'] ?? 'pending',
                            'role'    => $user['role'] ?? 'viewer',
                        ];
                    })->filter()->values()->toArray();
                } else {
                    $newUsers = collect($sharedUsersRaw)->filter(fn($user) => !$existingUsers->contains('user_id', $user['user_id']));
                    $newFormattedUsers = $newUsers->map(function ($user) {
                        $userModel = User::find($user['user_id']);
                        if (!$userModel) return null;
    
                        return [
                            'user_id' => $userModel->id,
                            'email'   => $userModel->email,
                            'status'  => 'pending',
                            'role'    => 'viewer',
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

            // 📦 Lấy tất cả các task đang dùng tag này
            $tasksInTag = Task::where('tag_id', $tag->id)->get();

            // 📤 Gửi realtime cập nhật cho các task này
            if ($tasksInTag->isNotEmpty()) {
                $this->sendRealTimeUpdateTasks($tasksInTag, 'update');
            }
    
            $newSharedUserIds = collect($formattedSharedUsers)->pluck('user_id')->toArray();
            $newUserIds = array_diff($newSharedUserIds, $oldSharedUserIds);
    
            // Gửi mail & thông báo cho người mới được mời
            if (!empty($newUserIds)) {
                $emails = User::whereIn('id', $newUserIds)->pluck('email')->filter();
                if ($emails->isNotEmpty()) {
                    $this->sendMail(Auth::user()->email, $emails, $tag);
                }
    
                foreach ($newUserIds as $newUserId) {
                    $newUser = User::find($newUserId);
                    if ($newUser) {
                        $newUser->notify(new NotificationEvent(
                            $newUser->id,
                            "Bạn đã được mời tham gia thẻ: {$tag->name}",
                            "{$this->URL_FRONTEND}/calendar/tag/{$tag->uuid}/invite",
                            "invite_to_tag"
                        ));
                    }
                }
            }
    
            $returnTag[] = $tag;
            $this->sendRealTimeUpdate($returnTag, 'update');
    
            // 🔔 Gửi thông báo nếu người chỉnh sửa là editor
            if (!$isOwner) {
                $owner = User::find($tag->user_id);
                $editor = Auth::user();
                $fullName = trim(($editor->first_name ?? '') . ' ' . ($editor->last_name ?? ''));
                if ($owner && $owner->id !== $editor->id) {
                    $owner->notify(new NotificationEvent(
                        $owner->id,
                        "{$fullName} vừa chỉnh sửa thẻ: {$tag->name}",
                        "",
                        "tag_updated_by_editor"
                    ));
                }
            }
    
            // 🔔 Gửi thông báo cho các shared user đã chấp nhận
            foreach ($formattedSharedUsers as $user) {
                if ($user['user_id'] !== $userId && $user['status'] === 'yes') {
                    $sharedUser = User::find($user['user_id']);
                    if ($sharedUser) {
                        $sharedUser->notify(new NotificationEvent(
                            $sharedUser->id,
                            "Thẻ {$tag->name} vừa được cập nhật",
                            "",
                            "tag_updated"
                        ));
                    }
                }
            }
    
            $owner = User::find($tag->user_id);
            return response()->json([
                'code'    => 200,
                'message' => 'Tag updated successfully',
                'data'    => [
                    'tag'   => array_merge($tag->toArray(), [
                        'shared_user' => $this->formatSharedUsers($tag->shared_user)
                    ]),
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
                        $message = "Thẻ '{$tagName}' đã bị xóa, các sự kiện liên quan {$taskList}.";
    
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
                "{$fullName} đã chấp nhận lời mời vào thẻ: {$tag->name}",
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
                "{$fullName} đã từ chối lời mời vào thẻ: {$tag->name}",
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
    
            Log::info("🔁 [leaveTag] Bắt đầu xử lý leave tag", [
                'user_id' => $userId,
                'tag_id' => $id
            ]);
    
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
    
            // 👉 Ghi nhớ các task mà người rời sẽ bị mất quyền sở hữu (trước khi chuyển)
            $tasksCreatedByUser = Task::where('user_id', $userId)
                ->where('tag_id', $tag->id)
                ->get();
            $tasksToRemoveFromLeaver = $tasksCreatedByUser->pluck('id');
    
            Log::info("✏️ [leaveTag] Chuyển quyền các task do user tạo", [
                'task_ids' => $tasksToRemoveFromLeaver,
                'new_owner_id' => $tag->user_id
            ]);
    
            foreach ($tasksCreatedByUser as $task) {
                $filteredAttendees = collect($task->attendees)
                    ->filter(fn($attendee) => $attendee['user_id'] != $tag->user_id)
                    ->values()
                    ->toArray();
    
                $task->update([
                    'attendees' => $filteredAttendees,
                    'user_id' => $tag->user_id,
                ]);
            }

            if ($tasksCreatedByUser->isNotEmpty()) {
                Log::info("📡 [leaveTag] Gửi realtime task cho chủ sở hữu sau khi chuyển quyền", [
                    'new_owner_id' => $tag->user_id,
                    'task_ids'     => $tasksCreatedByUser->pluck('id'),
                ]);
            
                $this->sendRealTimeUpdateTasks($tasksCreatedByUser, 'update', [$tag->user_id]);
            }
    
            // 🧹 Xóa user khỏi shared_user
            $newSharedUsers = $sharedUsers
                ->reject(fn($user) => $user['user_id'] == $userId)
                ->values()
                ->toArray();
    
            $tag->update(['shared_user' => $newSharedUsers]);
    
            Log::info("🧹 [leaveTag] Đã xóa user khỏi shared_user", [
                'remaining_shared_users' => $newSharedUsers
            ]);
    
            // 📤 Xóa user khỏi attendees của các task còn lại
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
    
            Log::info("📤 [leaveTag] Danh sách task đã xóa user khỏi attendees", [
                'task_ids' => collect($tasksLeft)->pluck('id')
            ]);
    
            // 📣 Gửi thông báo cho chủ tag
            $owner = User::find($tag->user_id);
            if ($owner) {
                $fullName = trim(($currentUser->first_name ?? '') . ' ' . ($currentUser->last_name ?? ''));
                $taskNames = collect($tasksLeft)->pluck('title')->filter()->values()->toArray();
                $taskNamesStr = !empty($taskNames) ? ' và các task: ' . implode(', ', $taskNames) : '';
    
                Log::info("📣 [leaveTag] Gửi thông báo đến chủ sở hữu", [
                    'owner_id' => $owner->id,
                    'from_user_id' => $currentUser->id
                ]);
    
                $owner->notify(new NotificationEvent(
                    $owner->id,
                    "{$fullName} đã rời khỏi thẻ: {$tag->name}{$taskNamesStr}",
                    "",
                    "leave_tag"
                ));
            }
    
            // 📡 Gửi realtime update tag cho tất cả
            Log::info("📡 [leaveTag] Gửi realtime cập nhật tag", [
                'tag_id' => $tag->id
            ]);
            $this->sendRealTimeUpdate([$tag], 'update');
    
            // 📡 Gửi realtime update task nếu có chỉnh attendees
            if (!empty($tasksLeft)) {
                Log::info("📡 [leaveTag] Gửi realtime cập nhật các task (attendees thay đổi)", [
                    'task_ids' => collect($tasksLeft)->pluck('id')
                ]);
                $this->sendRealTimeUpdateTasks($tasksLeft, 'update');
            }
    
            // 📡 Gửi realtime delete các task người rời bị mất quyền (vì bị chuyển chủ)
            if ($tasksToRemoveFromLeaver->isNotEmpty()) {
                $tasksLost = Task::whereIn('id', $tasksToRemoveFromLeaver)->get();
    
                Log::info("📡 [leaveTag] Gửi realtime riêng cho người rời khỏi tag", [
                    'user_id' => $userId,
                    'lost_task_ids' => $tasksToRemoveFromLeaver
                ]);
    
                $this->sendRealTimeUpdateTasks($tasksLost, 'delete', [$userId]);
  
            }
    
            return response()->json([
                'code' => 200,
                'message' => 'Successfully left the tag and removed from related tasks',
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('❌ [leaveTag] Exception: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
    
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
    
            // 1. Xóa user khỏi shared_user
            $newSharedUsers = $sharedUsers
                ->reject(fn($user) => $user['user_id'] == $userIdToRemove)
                ->values()
                ->toArray();
            $tag->update(['shared_user' => $newSharedUsers]);
    
            $tasksUpdated = [];
    
            // 2. Chuyển quyền task mà user bị xóa đã tạo sang chủ tag
            $tasksCreatedByUser = Task::where('user_id', $userIdToRemove)
                ->where('tag_id', $tag->id)
                ->get();
    
            foreach ($tasksCreatedByUser as $task) {
                // Xóa chủ tag khỏi attendees nếu có
                $filteredAttendees = collect($task->attendees ?? [])
                    ->filter(fn($attendee) => $attendee['user_id'] != $tag->user_id)
                    ->values()
                    ->toArray();
    
                $task->update([
                    'attendees' => $filteredAttendees,
                    'user_id' => $tag->user_id,
                ]);
    
                $tasksUpdated[] = $task;
            }
    
            // 3. Nếu cần thì xóa khỏi attendees trong task
            if (!$keepInTasks) {
                foreach ($tag->tasks as $task) {
                    $attendees = collect($task->attendees ?? [])
                        ->reject(fn($attendee) => $attendee['user_id'] == $userIdToRemove)
                        ->values()
                        ->toArray();
    
                    $task->update(['attendees' => $attendees]);
                    $tasksUpdated[] = $task;
                }
            }
    
            // 4. Gửi thông báo và email nếu người bị xóa đã chấp nhận
            $removedUser = User::find($userIdToRemove);
            $owner = User::find($tag->user_id);
    
            $wasAccepted = $sharedUsers
                ->firstWhere('user_id', $userIdToRemove)['status'] ?? null;
    
            if ($removedUser && $owner && $wasAccepted === 'yes') {
                $taskNames = collect($tasksUpdated)->pluck('title')->filter()->values()->toArray();
                $taskListStr = implode(', ', $taskNames);
    
                $message = $keepInTasks
                    ? "Bạn đã bị xóa khỏi thẻ: {$tag->name}"
                    : (empty($taskListStr)
                        ? "Bạn đã bị xóa khỏi thẻ: {$tag->name}"
                        : "Bạn đã bị xóa khỏi thẻ: {$tag->name} và khỏi các sự kiện: {$taskListStr}");
    
                // Gửi thông báo
                $removedUser->notify(new NotificationEvent(
                    $removedUser->id,
                    $message,
                    "",
                    "removed_from_tag"
                ));
    
                // Gửi email
                $ownerFullName = trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? ''));
                Mail::to($removedUser->email)->queue(new RemoveFromTagMail(
                    env('MAIL_USERNAME'),
                    $ownerFullName,
                    $tag,
                    $keepInTasks
                ));
            }
    
            // 5. Gửi realtime update
            $this->sendRealTimeUpdate([$tag], 'update');
    
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