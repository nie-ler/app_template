<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::group([
    'prefix' => '/{tenant}',
    'middleware' => [
        InitializeTenancyByPath::class, 
        'auth', 
        'verified', 
        'web', 
        'app.inertia'
    ],
], function () {
    Route::get('/app_test', function () {
        $tenant = tenant();
        return [
            'user_id' => auth()->user()->id,
            'tenant_id' => $tenant->id,
        ];
    })->name('app');
});


