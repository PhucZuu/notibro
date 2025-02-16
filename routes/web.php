<?php

use Illuminate\Support\Facades\Route;

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
    Route::view('/colors', 'admin.colors.index')->name('admin.colors');
    Route::view('/colors/create', 'admin.colors.create')->name('admin.createcolors');
    Route::view('/dashboard', 'admin.index')->name('admin.dashboard');
});
