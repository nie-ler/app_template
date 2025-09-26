<?php

use Illuminate\Support\Facades\Route;
use Tests\Feature;
use Tests\TestCase;

use Nilit\LaraBoilerCore\Models\User;

// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('[login_route_test] can reach the route /login', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
});

it('[database_connection_test] can reach the test-database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('MyMandate');
    expect(Schema::connection('central')->hasTable('users'))->toBeTrue();
    expect(Schema::connection('central')->hasTable('migrations'))->toBeTrue();
    expect(Schema::connection('central')->hasTable('permissions'))->toBeTrue();
});
