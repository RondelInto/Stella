<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// This is an internal business system, so there is no public landing page.
// Logged-in users go to the dashboard; guests are redirected to the login page by the 'auth' middleware.
Route::redirect('/', '/dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
