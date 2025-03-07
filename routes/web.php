<?php

use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'userStatistics'])->name('admin.dashboard');

        Route::resource('colors', ColorController::class);
        Route::patch('colors/{color}/restore', [ColorController::class, 'restore'])->name('colors.restore');
        Route::delete('colors/{color}/force-delete', [ColorController::class, 'forceDelete'])->name('colors.forceDelete');

        Route::resource('roles', RoleController::class);
        Route::patch('roles/{role}/restore', [RoleController::class, 'restore'])->name('roles.restore');
        Route::delete('roles/{role}/force-delete', [RoleController::class, 'forceDelete'])->name('roles.forceDelete');
    });

});
Broadcast::routes(['middleware' => ['auth:sanctum']]); 