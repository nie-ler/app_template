<?php

use Illuminate\Support\Facades\Route;
use Tests\Feature;
use Tests\TestCase;

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Database\Seeders\PermissionSeeder;

// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // $this->seed(PermissionSeeder::class);

    // Create a tenant for testing
    $tenant = Tenant::create([
        'id' => 'test-' . uniqid(),
        'name' => 'Test Tenant',
        'email' => 'test@example.com',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Create test user
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $user->assignRole('owner');
    $user->tenant_id = $tenant->id;
    $user->save();
    
    // Create a tenant for testing
    $wrong_tenant = Tenant::create([
        'id' => 'test-' . uniqid(),
        'name' => 'Test Tenant',
        'email' => 'test_wrong@example.com',
    ]);
    
    $this->user = $user;
    $this->tenant = $tenant;
    $this->wrong_tenant = $wrong_tenant;
});


afterEach(function () {
    // Clean up: end tenancy
    $this->tenant->delete();
    $this->wrong_tenant->delete();
    tenancy()->end();
    
});

it('[user_authentication_test] can login', function () {
    $response = $this->post('/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $response = $this->actingAs($this->user)->get("/{$this->tenant->id}/users");
    $response->assertStatus(200);
});

it('[user_authentication_test] can not acces wrong tenant', function () {
    $response = $this->post('/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $response = $this->actingAs($this->user)->get("/{$this->wrong_tenant->id}/users");
    $response->assertStatus(403);
});

it('[user_authentication_test] can edit user', function () {
    // Create 2nd test user
    $user_2 = User::factory()->create([
        'email' => 'test_xy@example.com',
        'name' => 'Test User 2',
    ]);

    $user_2->assignRole('user');
    $user_2->tenant_id = $this->tenant->id;
    $user_2->save();

    $response = $this->post('/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $response = $this->actingAs($this->user)->get("/{$this->tenant->id}/users/{$user_2->id}/edit");
    $response->assertStatus(200);
});
