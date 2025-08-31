<?php

use Illuminate\Support\Facades\Route;
use Tests\Feature_Memory;
use Tests\TestCase;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('[login_route_test] can reach the route /login', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
});

it('[database_connection_test] can reach the test-database', function () {
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    expect(Schema::connection('sqlite')->hasTable('users'))->toBeTrue();
    expect(Schema::connection('sqlite')->hasTable('migrations'))->toBeTrue();
    expect(Schema::connection('sqlite')->hasTable('permissions'))->toBeTrue();
});
