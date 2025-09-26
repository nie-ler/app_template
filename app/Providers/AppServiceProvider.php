<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Nilit\LaraBoilerCore\Models\Tenant;
use Laravel\Cashier\Cashier;

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
        //
        Cashier::useCustomerModel(Tenant::class);
    }
}
