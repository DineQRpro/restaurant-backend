<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPermissionGates();
    }

    /**
     * Register a Gate for every permission slug so that
     * $this->authorize('orders.create') works in controllers,
     * and Gate::allows('orders.create') works anywhere.
     */
    private function registerPermissionGates(): void
    {
        Gate::before(function ($user, $ability) {
            if ($user->hasPermission($ability)) {
                return true;
            }

            // Return null (not false) so other gates/policies can still apply
            return null;
        });
    }
}
