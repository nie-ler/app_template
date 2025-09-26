<?php

use Nilit\LaraBoilerCore\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    // Create a tenant for testing
    $tenant = \Nilit\LaraBoilerCore\Models\Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Create admin user with all permissions
    $adminUser = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    
    $adminRole = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $viewUsersPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'view users']);
    $createUsersPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'create users']);
    $editUsersPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'edit users']);
    $deleteUsersPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'delete users']);
    
    $adminRole->givePermissionTo([
        $viewUsersPermission,
        $createUsersPermission,
        $editUsersPermission,
        $deleteUsersPermission
    ]);
    
    $adminUser->assignRole($adminRole);
    
    // Create regular user with no special permissions
    $regularUser = User::factory()->create([
        'email' => 'user@example.com',
    ]);
    
    // Make these users available to the tests
    $this->adminUser = $adminUser;
    $this->regularUser = $regularUser;
    $this->tenant = $tenant;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// User Listing Tests
it('[user_listing_happy_path] allows authorized user to view user list', function () {
    $this->actingAs($this->adminUser)
        ->get(route('users.index'))
        ->assertStatus(200)
        ->assertSee($this->adminUser->name)
        ->assertSee($this->regularUser->name);
});

it('[user_listing_validation] validates filters and sorting parameters', function () {
    $this->actingAs($this->adminUser)
        ->get(route('users.index', ['sort' => 'invalid_column']))
        ->assertStatus(422);
        
    $this->actingAs($this->adminUser)
        ->get(route('users.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertStatus(200);
});

it('[user_listing_permission_denied] denies access to user without view permission', function () {
    $this->actingAs($this->regularUser)
        ->get(route('users.index'))
        ->assertStatus(403);
});

it('[user_listing_edge_empty] handles empty user list gracefully', function () {
    // Delete all users
    User::query()->delete();
    
    $this->actingAs($this->adminUser)
        ->get(route('users.index'))
        ->assertStatus(200)
        ->assertDontSee('admin@example.com')
        ->assertSee('No users found');
});

it('[user_listing_edge_page_overflow] handles page overflow gracefully', function () {
    $this->actingAs($this->adminUser)
        ->get(route('users.index', ['page' => 999]))
        ->assertStatus(200)
        ->assertSee('No users found');
});
