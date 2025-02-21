<?php

use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\api\Timezone\TimezoneController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Api\User\AdminUser\AdminUserController;

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
//     return view('admin.user.index');
// });


Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.index');
        })->name('admin.dashboard');

        Route::resource('colors', ColorController::class);
        Route::patch('colors/{color}/restore', [ColorController::class, 'restore'])->name('colors.restore');
        Route::delete('colors/{color}/force-delete', [ColorController::class, 'forceDelete'])->name('colors.forceDelete');

        Route::resource('roles', RoleController::class);
        Route::patch('roles/{role}/restore', [RoleController::class, 'restore'])->name('roles.restore');
        Route::delete('roles/{role}/force-delete', [RoleController::class, 'forceDelete'])->name('roles.forceDelete');

        // Timezone Routes
        Route::get('/timezones', [TimezoneController::class, 'index'])->name('timezones');
        Route::get('/timezones/create', [TimezoneController::class, 'create'])->name('timezones.create');
        Route::post('/timezones', [TimezoneController::class, 'store'])->name('timezones.store');

        //User for admin
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/{id}', [UserController::class, 'show'])->name('admin.users.show');
        Route::post('/users/{id}/delete', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/users/{id}/ban', [UserController::class, 'ban'])->name('admin.users.ban');
        Route::patch('/users/{id}/unlock', [UserController::class, 'unlock'])->name('admin.users.unlock');
        Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('admin.users.forceDelete');
    });
});
