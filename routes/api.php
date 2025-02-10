<?php

use App\Http\Controllers\Api\Auth\AuthController;
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
Route::post('/auth/verify',   [AuthController::class, 'verifyOtp']);
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);

Route::post('/user/send-reset-password-mail',[AuthController::class, 'sendOtpResetPassword']);
Route::post('/user/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group( function() {
    Route::post('/auth/logout',   [AuthController::class,'logout']);

    Route::get('/user',                 [UserController::class,'profile']);
    Route::put('/user/update-profile',  [UserController::class,'updateProfile']);
    Route::put('/user/change-password', [AuthController::class,'changePassword']);    
});