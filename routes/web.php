<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryItemImportController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\SopController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockLevelController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionPlanningController;
use App\Http\Controllers\ProductionRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesImportController;
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

// Stock counts: record at own location ('record-stock-counts'); viewing needs 'view-inventory'.
// 'create' is registered BEFORE '{count}' so "/counts/create" isn't read as a count ID.
Route::middleware(['auth', 'can:record-stock-counts'])->prefix('inventory')->group(function () {
    Route::get('/counts/create', [StockCountController::class, 'create'])->name('stock-counts.create');
    Route::post('/counts', [StockCountController::class, 'store'])->name('stock-counts.store');
    Route::put('/counts/{count}', [StockCountController::class, 'update'])->name('stock-counts.update');
    Route::delete('/counts/{count}', [StockCountController::class, 'destroy'])->name('stock-counts.destroy');
});

Route::middleware(['auth', 'can:view-inventory'])->prefix('inventory')->group(function () {
    Route::get('/counts', [StockCountController::class, 'index'])->name('stock-counts.index');
    Route::get('/counts/discrepancies', [StockCountController::class, 'discrepancies'])->name('stock-counts.discrepancies');
    Route::get('/counts/{count}', [StockCountController::class, 'show'])->name('stock-counts.show');
});

Route::middleware(['auth', 'can:record-inventory-transactions'])->prefix('inventory')->group(function () {
    Route::get('/transactions/create', [InventoryTransactionController::class, 'create'])->name('inventory-transactions.create');
    Route::post('/transactions', [InventoryTransactionController::class, 'store'])->name('inventory-transactions.store');
});

/*
|--------------------------------------------------------------------------
| Stock transfers (commissary -> branch)
|--------------------------------------------------------------------------
| Route middleware checks the ROLE permission; StockTransferPolicy checks location + status.
*/
Route::middleware(['auth', 'can:prepare-stock-transfers'])->group(function () {
    Route::get('/transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
    Route::post('/transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
    Route::get('/transfers/{transfer}/edit', [StockTransferController::class, 'edit'])->name('stock-transfers.edit');
    Route::put('/transfers/{transfer}', [StockTransferController::class, 'update'])->name('stock-transfers.update');
    Route::post('/transfers/{transfer}/release', [StockTransferController::class, 'release'])->name('stock-transfers.release');
});

Route::middleware(['auth', 'can:receive-stock-transfers'])->group(function () {
    Route::post('/transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
});

Route::middleware(['auth', 'can:cancel-stock-transfers'])->group(function () {
    Route::post('/transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
});

Route::middleware(['auth', 'can:view-stock-transfers'])->group(function () {
    Route::get('/transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
    Route::get('/transfers/{transfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
    Route::get('/transfers/{transfer}/print', [StockTransferController::class, 'print'])->name('stock-transfers.print');
});

/*
|--------------------------------------------------------------------------
| Food production
|--------------------------------------------------------------------------
*/
// Sellable products (Management, same permission as the item list).
Route::middleware(['auth', 'can:manage-inventory-items'])->group(function () {
    Route::resource('products', ProductController::class)->except(['show', 'destroy']);
    Route::patch('/products/{product}/status', [ProductController::class, 'updateStatus'])->name('products.status');
});

Route::middleware(['auth', 'can:record-production'])->group(function () {
    Route::get('/production/create', [ProductionRecordController::class, 'create'])->name('production.create');
    Route::post('/production', [ProductionRecordController::class, 'store'])->name('production.store');
});

Route::middleware(['auth', 'can:view-production'])->group(function () {
    Route::get('/production', [ProductionRecordController::class, 'index'])->name('production.index');
    Route::get('/production/planning', [ProductionPlanningController::class, 'index'])->name('production.planning');
    Route::get('/production/{record}', [ProductionRecordController::class, 'show'])->name('production.show');
});

/*
|--------------------------------------------------------------------------
| Standard Operating Procedures
|--------------------------------------------------------------------------
| Manage: 'manage-sops'. View: 'view-sops' + SopRecordPolicy (role, status, effective date).
*/
Route::middleware(['auth', 'can:manage-sops'])->group(function () {
    Route::get('/sops/create', [SopController::class, 'create'])->name('sops.create');
    Route::post('/sops', [SopController::class, 'store'])->name('sops.store');
    Route::post('/sops/preview', [SopController::class, 'preview'])->name('sops.preview');
    Route::get('/sops/{sop}/edit', [SopController::class, 'edit'])->name('sops.edit');
    Route::put('/sops/{sop}', [SopController::class, 'update'])->name('sops.update');
    Route::patch('/sops/{sop}/status', [SopController::class, 'updateStatus'])->name('sops.status');
    Route::get('/sops/{sop}/versions/{version}', [SopController::class, 'showVersion'])->name('sops.versions.show');
});

Route::middleware(['auth', 'can:view-sops'])->group(function () {
    Route::get('/sops', [SopController::class, 'index'])->name('sops.index');
    Route::get('/sops/{sop}', [SopController::class, 'show'])->name('sops.show');
    Route::get('/sops/{sop}/print', [SopController::class, 'print'])->name('sops.print');
});

/*
|--------------------------------------------------------------------------
| POS sales import (module 7)
|--------------------------------------------------------------------------
| Reads files EXPORTED from each location's POS. Never connects to the POS. Management only.
*/
Route::middleware(['auth', 'can:import-pos-sales'])->prefix('sales-imports')->name('sales-imports.')->group(function () {
    Route::get('/', [SalesImportController::class, 'index'])->name('index');
    Route::get('/create', [SalesImportController::class, 'create'])->name('create');
    Route::post('/', [SalesImportController::class, 'store'])->name('store');
    Route::get('/template', [SalesImportController::class, 'template'])->name('template');
    Route::get('/unmatched', [SalesImportController::class, 'unmatched'])->name('unmatched');
    Route::post('/unmatched/link', [SalesImportController::class, 'link'])->name('link');
    Route::get('/{batch}', [SalesImportController::class, 'show'])->name('show');
    Route::delete('/{batch}', [SalesImportController::class, 'destroy'])->name('destroy');
});

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
| 'view-branch-reports' = Management + Branch Managers. The controller limits Branch Managers
| to their own branch and shows peso values only to Management ('view-all-reports').
*/
Route::middleware(['auth', 'can:view-branch-reports'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/inventory-movement', [ReportController::class, 'inventoryMovement'])->name('inventory-movement');
    Route::get('/discrepancies', [ReportController::class, 'discrepancies'])->name('discrepancies');
    Route::get('/production', [ReportController::class, 'production'])->name('production');
    Route::get('/transfers', [ReportController::class, 'transfers'])->name('transfers');
});

// Management (Admin): branches and commissary
Route::middleware(['auth', 'can:manage-locations'])->group(function () {
    Route::resource('locations', LocationController::class)->except(['show', 'destroy']);
    Route::patch('/locations/{location}/status', [LocationController::class, 'updateStatus'])->name('locations.status');
});

require __DIR__.'/auth.php';
