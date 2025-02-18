<?php

use App\Http\Controllers\Api\Color\ColorController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\Setting\SettingController;
use App\Http\Controllers\api\Task\TaskController;
use App\Http\Controllers\api\Timezone\TimezoneController;
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

Route::post('/user/send-reset-password-mail', [AuthController::class, 'sendOtpResetPassword']);
Route::post('/user/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',   [AuthController::class, 'logout']);

    Route::get('/user',                 [UserController::class, 'profile']);
    Route::put('/user/update-profile',  [UserController::class, 'updateProfile']);
    Route::put('/user/change-password', [AuthController::class, 'changePassword']);

    Route::get('/setting', [SettingController::class, 'setting']);
    Route::put('/setting/change', [SettingController::class, 'changeSetting']);

    // TASK
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::put('/tasks/{id}', [TaskController::class, 'updateTask']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

    //List and get timezone
    Route::get('/timezones',        [TimezoneController::class, 'index']);
    Route::get('/timezones/{id}',   [TimezoneController::class, 'show']);
   

    Route::middleware(['admin'])->prefix('admin')->group(function () {
        //User
        Route::get('/users',                [UserController::class, 'getAllUser']);
        Route::get('/users/{id}',           [UserController::class, 'show']);
        Route::put('/users/{id}/permission', [UserController::class, 'changePermission']);
        Route::delete('/users/{id}/ban',    [UserController::class, 'ban']);
        Route::post('/users/{id}/unlock',   [UserController::class, 'unlock']);

        //Roles
        Route::get('/roles',        [RoleController::class, 'index']);
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
    });
});
