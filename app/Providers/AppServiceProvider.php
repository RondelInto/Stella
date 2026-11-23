<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGates();
    }

    /**
     * Turn every permission in config/permissions.php into a Laravel Gate.
     * Example: config 'manage-users' => ['admin'] becomes Gate 'manage-users'.
     * Deactivated users never pass any gate.
     */
    private function registerPermissionGates(): void
    {
        foreach (array_keys(config('permissions', [])) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
