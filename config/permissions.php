<?php

/*
|--------------------------------------------------------------------------
| Role Permissions (who can do what)
|--------------------------------------------------------------------------
|
| Each permission lists the role slugs that are allowed to perform it.
| This is the ONLY place to change what a role can do.
|
| Sources: Use Case Diagram (Figure 6), Security Plan (Chapter 3), and the
| initial role definitions in the project instructions.
| Items marked "confirmed" were approved by the team on 2026-11-23.
|
| Role slugs: admin, branch_manager, commissary_staff, branch_staff
|
| How it is used:
|   Routes:      Route::get(...)->middleware('can:manage-users');
|   Controllers: Gate::authorize('manage-users');
|   Blade:       @can('manage-users') ... @endcan
|
| NOTE: Except for Management (admin), users are also limited to their OWN
| location. See User::canAccessLocation().
|
*/

return [

    // ---- User and location management (Management only) ----
    'manage-users' => ['admin'],              // create/edit/deactivate employee accounts, assign roles
    'manage-locations' => ['admin'],

    // ---- Inventory ----
    'manage-inventory-items' => ['admin'],    // item list, categories, unit costs, reorder levels
    'view-inventory' => ['admin', 'branch_manager', 'commissary_staff', 'branch_staff'],
    'record-inventory-transactions' => ['admin', 'branch_manager', 'commissary_staff', 'branch_staff'],
    'record-stock-counts' => ['branch_manager', 'commissary_staff', 'branch_staff'], // confirmed (DFD: cashier submits closing count)

    // ---- Stock transfers ----
    'view-stock-transfers' => ['admin', 'branch_manager', 'commissary_staff', 'branch_staff'],
    'prepare-stock-transfers' => ['commissary_staff'],   // create + release (commissary sends stock)
    'receive-stock-transfers' => ['branch_manager', 'branch_staff'], // confirmed (DFD: cashier signs receiving record)
    'cancel-stock-transfers' => ['admin', 'commissary_staff'],       // confirmed: branch managers cannot cancel

    // ---- Food production ----
    'record-production' => ['commissary_staff'],                     // confirmed: admin does not record production
    'view-production' => ['admin', 'branch_manager', 'commissary_staff'], // confirmed: branch staff cannot view

    // ---- POS sales and analysis (Management only, per Security Plan) ----
    'import-pos-sales' => ['admin'],
    'view-sales-analysis' => ['admin'],
    'run-sales-analysis' => ['admin'],

    // ---- SOPs ----
    'manage-sops' => ['admin'],
    'view-sops' => ['admin', 'branch_manager', 'commissary_staff', 'branch_staff'], // only SOPs assigned to their role

    // ---- Reports ----
    'view-all-reports' => ['admin'],                       // centralized, all locations
    'view-branch-reports' => ['admin', 'branch_manager'],  // own branch only for managers

];
