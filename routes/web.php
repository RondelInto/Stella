<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// This is an internal business system, so there is no public landing page.
// Logged-in users go to the dashboard; guests are redirected to the login page by the 'auth' middleware.
Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // No self-service account deletion: accounts are deactivated by Management to keep the audit trail.
});

/*
|--------------------------------------------------------------------------
| Management (Admin): employee accounts and roles
|--------------------------------------------------------------------------
| Every route is protected on the SERVER by 'can:manage-users' (config/permissions.php).
*/
Route::middleware(['auth', 'can:manage-users'])->group(function () {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

    Route::resource('roles', RoleController::class)->only(['index', 'edit', 'update']);
});

require __DIR__.'/auth.php';
