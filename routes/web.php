<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryItemImportController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\StockLevelController;
use App\Http\Controllers\LocationController;
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

/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
| Item list/detail: anyone who can view inventory. Add/edit: Management only.
| NOTE: the 'manage' routes are registered first so '/inventory/items/create'
| is not mistaken for an item ID by the 'show' route.
*/
Route::middleware(['auth', 'can:manage-inventory-items'])->prefix('inventory')->group(function () {
    Route::resource('categories', InventoryCategoryController::class)
        ->except(['show'])
        ->names('inventory-categories');

    Route::get('/items/import', [InventoryItemImportController::class, 'create'])->name('inventory-items.import');
    Route::post('/items/import', [InventoryItemImportController::class, 'store'])->name('inventory-items.import.store');
    Route::get('/items/import/template', [InventoryItemImportController::class, 'template'])->name('inventory-items.import.template');

    Route::resource('items', InventoryItemController::class)
        ->only(['create', 'store', 'edit', 'update'])
        ->names('inventory-items');
    Route::patch('/items/{item}/status', [InventoryItemController::class, 'updateStatus'])->name('inventory-items.status');
});

Route::middleware(['auth', 'can:view-inventory'])->prefix('inventory')->group(function () {
    Route::get('/items', [InventoryItemController::class, 'index'])->name('inventory-items.index');
    Route::get('/items/{item}', [InventoryItemController::class, 'show'])->name('inventory-items.show');

    Route::get('/stock', [StockLevelController::class, 'index'])->name('stock-levels.index');
    Route::get('/transactions', [InventoryTransactionController::class, 'index'])->name('inventory-transactions.index');
});

Route::middleware(['auth', 'can:record-inventory-transactions'])->prefix('inventory')->group(function () {
    Route::get('/transactions/create', [InventoryTransactionController::class, 'create'])->name('inventory-transactions.create');
    Route::post('/transactions', [InventoryTransactionController::class, 'store'])->name('inventory-transactions.store');
});

// Management (Admin): branches and commissary
Route::middleware(['auth', 'can:manage-locations'])->group(function () {
    Route::resource('locations', LocationController::class)->except(['show', 'destroy']);
    Route::patch('/locations/{location}/status', [LocationController::class, 'updateStatus'])->name('locations.status');
});

require __DIR__.'/auth.php';
