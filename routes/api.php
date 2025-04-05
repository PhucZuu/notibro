<?php

use App\Http\Controllers\Api\Stat\AdminStatController;
use App\Http\Controllers\Api\Color\ColorController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\AuthGoogleController;
use App\Http\Controllers\Api\Chat\TaskGroupChatController;
use App\Http\Controllers\Api\FileEntries\FileEntryController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\OpenAI\OpenAIController;
use App\Http\Controllers\Api\Package\StoragePackageController;
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\S3UploadFile\S3SUploadController;
use App\Http\Controllers\Api\Setting\SettingController;
use App\Http\Controllers\Api\Stat\StatController;
use App\Http\Controllers\Api\Tag\TagController;
use App\Http\Controllers\Api\Task\TaskController;
use App\Http\Controllers\Api\Timezone\TimezoneController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/auth/login',    [AuthController::class, 'login'])->name('login');
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/verify',   [AuthController::class, 'verifyEmail']);
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);

Route::prefix('/auth/google')->group(function () {
    Route::get('/redirect', [AuthGoogleController::class, 'redirect']);
    Route::get('/callback', [AuthGoogleController::class, 'callback']);
    Route::get('/get-google-user', [AuthGoogleController::class, 'getGoogleUser'])->middleware('auth:sanctum');
});

Route::post('/user/send-reset-password-mail', [AuthController::class, 'sendOtpResetPassword']);
Route::post('/user/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',   [AuthController::class, 'logout']);

    Route::get('/user',                 [UserController::class, 'profile']);
    Route::put('/user/update-profile',  [UserController::class, 'updateProfile']);
    Route::put('/user/change-password', [AuthController::class, 'changePassword']);

    Route::get('/setting', [SettingController::class, 'setting']);
    Route::put('/setting/change', [SettingController::class, 'changeSetting']);

    // invite link
    Route::post('/event/{uuid}/accept', [TaskController::class,'acceptInvite']);
    Route::post('/event/{uuid}/refuse', [TaskController::class,'refuseInvite']);
    Route::get('/event/{uuid}/invite', [TaskController::class,'show']);

    Route::post('/tags/{id}/accept', [TagController::class, 'acceptTagInvite']);
    Route::post('/tags/{id}/refuse', [TagController::class, 'declineTagInvite']);
    Route::get('/tags/{id}/invite', [TagController::class, 'show']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}/delete-one', [NotificationController::class, 'destroy']);
    Route::delete('/notifications/delete-all', [NotificationController::class, 'destroyAll']);
    
    // TASK
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/upComingTasks', [TaskController::class, 'getUpComingTasks']);
    Route::get('/tasks/{id}/show', [TaskController::class,'showOne']);
    Route::put('/tasks/{id}', [TaskController::class, 'updateTask']);
    Route::put('/tasks/{id}/onDrag', [TaskController::class, 'updateTaskOnDrag']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::delete('/tasks/trash/forceDestroy', [TaskController::class, 'forceDestroy']);
    Route::put('/tasks/trash/restoreTask', [TaskController::class, 'restoreTask']);
    Route::get('/tasks/getTrashedTasks', [TaskController::class, 'getTrashedTasks']);
    Route::put('/tasks/{id}/attendeeLeaveTask', [TaskController::class, 'attendeeLeaveTask']);

    // GROUP CHAT TASK

    Route::delete('/task-groups-chat/{groupId}/leave', [TaskGroupChatController::class, 'leaveGroup']);
    Route::delete('/group/{taskGroupId}/remove-member/{userId}', [TaskGroupChatController::class, 'removeMember']);
    Route::post('/group/message/send', [TaskGroupChatController::class, 'sendMessage']);
    Route::get('/task/{taskId}/messages', [TaskGroupChatController::class, 'getMessages']);

    //OpenAi
    Route::post('/ai/extract-fields', [OpenAIController::class, 'extractFields']);

    //List and get timezone
    Route::get('/timezones',        [TimezoneController::class, 'index']);
    Route::get('/timezones/{id}',   [TimezoneController::class, 'show']);
   
    //Tag
    Route::get('/tags', [TagController::class, 'index']);
    Route::get('/tags/sharedTags', [TagController::class, 'getSharedTag']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::get('/tags/{id}', [TagController::class, 'show']);
    Route::put('/tags/{id}', [TagController::class, 'update']);
    Route::delete('/tags/{id}', [TagController::class, 'destroy']);
    Route::post('/tags/{id}/leave', [TagController::class, 'leaveTag']);

    //Stat for user
        Route::get('/stats/completion-rate', [StatController::class, 'completionRate']);
        Route::get('/stats/busiest-day', [StatController::class, 'busiestDay']);
        Route::get('/stats/work-streak', [StatController::class, 'workStreak']);
        Route::get('/stats/total-tasks', [StatController::class, 'totalTasks']);

    // Get list guest
    Route::get('/guest', [UserController::class,'guest']);

    Route::get('/tasks/filter', [TaskController::class, 'search']);

    // S3 upload
    Route::post('/s3/upload', [S3SUploadController::class, 'createPresignedUrl']);
    Route::get('/s3/dowload', [S3SUploadController::class, 'getUrlDownloadFile']);

    // File entries
    Route::post('/file-entry/store/file', [FileEntryController::class, 'saveFile']);
    Route::get('/file-entry/{taskId}/files', [FileEntryController::class, 'getListFile']);
    Route::delete('/file-entry/delete', [FileEntryController::class, 'deleteFiles']);
    
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        //User
        Route::get('/users',                [UserController::class, 'getAllUser']);
        Route::get('/users/ban', [UserController::class, 'getBanUsers']);
        Route::get('/users/{id}',           [UserController::class, 'show']);
        Route::put('/users/{id}/permission', [UserController::class, 'changePermission']);
        Route::delete('/users/{id}/ban',    [UserController::class, 'ban']);
        Route::post('/users/{id}/unlock',   [UserController::class, 'unlock']);
        Route::post('/users/{id}/editAccount',   [UserController::class, 'editAccount']);
        Route::get('/users/{id}/infoAccount',   [UserController::class, 'infoAccount']);

        //Roles
        Route::get('/roles',        [RoleController::class, 'index']);
        Route::get('/roles/trashed', [RoleController::class, 'trashed']);
        Route::get('/roles/{id}',   [RoleController::class, 'show']);
        Route::post('/roles',       [RoleController::class, 'store']);
        Route::put('/roles/{id}',   [RoleController::class, 'update']);
        Route::delete('/roles/{id}/delete', [RoleController::class, 'delete']);
        Route::delete('/roles/{id}/forceDelete', [RoleController::class, 'forceDelete']);
        Route::put('roles/{id}/restore', [RoleController::class, 'restore']);

        //Timezone for admin
        Route::post('/timezones',       [TimezoneController::class, 'store']);
        Route::put('/timezones/{id}',   [TimezoneController::class, 'update']);
        Route::delete('/timezones/{id}/delete', [TimezoneController::class, 'delete']);
        Route::delete('/timezones/{id}/forceDelete', [TimezoneController::class, 'forceDelete']);
        Route::put('timezones/{id}/restore', [TimezoneController::class, 'restore']);

        Route::get('/colors', [ColorController::class, 'index']);
        Route::post('/colors', [ColorController::class, 'store']);
        Route::get('/colors/{id}', [ColorController::class, 'show']);
        Route::put('/colors/{id}', [ColorController::class, 'update']);
        Route::delete('/colors/{id}', [ColorController::class, 'destroy']);
        Route::patch('colors/{id}/restore', [ColorController::class, 'restore'])
            ->name('colors.restore');
        Route::delete('colors/{id}/force', [ColorController::class, 'destroyPermanent'])
            ->name('colors.forceDestroy');

        //PACKAGE

        Route::apiResource('/packages', StoragePackageController::class);
        Route::get('packages/search', [StoragePackageController::class, 'search']);
        Route::put('packages/{id}', [StoragePackageController::class, 'update']);
        Route::delete('packages/{id}', [StoragePackageController::class, 'destroy']); 
        Route::delete('packages/{id}/force', [StoragePackageController::class, 'forceDelete']);

        //Stat for admin
        Route::get('/stats/total-users', [AdminStatController::class, 'totalUsers']);
        Route::get('/stats/total-tasks', [AdminStatController::class, 'totalTasks']);
        Route::get('/stats/task-count-by-user', [AdminStatController::class, 'topTaskCreators']);
    });
});
