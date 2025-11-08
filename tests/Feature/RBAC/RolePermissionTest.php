<?php

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Role;
use Nilit\LaraBoilerCore\Models\Permission;
use Nilit\LaraBoilerCore\Models\Tenant;

beforeEach(function () {
    // Create a tenant for testing
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
        'email' => 'test-tenant-' . uniqid() . '@example.com',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Create users with different roles
    $adminUser = User::factory()->create([
        'email' => 'admin' . uniqid() . '@example.com',
        'name' => 'Admin User',
    ]);
    $adminUser->assignRole('owner');
    $adminUser->tenant_id = $tenant->id;
    $adminUser->save();
    
    $userManager = User::factory()->create([
        'email' => 'editor' . uniqid() . '@example.com',
        'name' => 'Editor User',
        'tenant_id' => $tenant->id
    ]);
    $userManager->assignRole('user manager');
    
    $ownerUser = User::factory()->create([
        'email' => 'owner' . uniqid() . '@example.com',
        'name' => 'Tenant Owner',
        'tenant_id' => $tenant->id
    ]);
    $ownerUser->assignRole('owner');
    
    $regularUser = User::factory()->create([
        'email' => 'regular' . uniqid() . '@example.com',
        'name' => 'Regular User',
    ]);
    $regularUser->assignRole('user');
    $regularUser->tenant_id = $tenant->id;
    $regularUser->save();
    
    // Make these users available to the tests
    $this->adminUser = $adminUser;
    $this->userManager = $userManager;
    $this->ownerUser = $ownerUser;
    $this->regularUser = $regularUser;
    $this->tenant = $tenant;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Role and Permission Management Tests
it('allows assigning and changing roles [001]', function () {
    $this->actingAs($this->adminUser);
    $this->assertAuthenticated();

    // Admin assigns editor role to regular user
    $response = $this->post(route('users.assign-role', ['tenant' => $this->tenant, 'user' => $this->regularUser]), [
            'role' => 'user manager'
        ]);
    
    $response->assertStatus(302); // Redirect on success
    
    // Check if the role was assigned
    $this->regularUser->refresh();
    expect($this->regularUser->hasRole('user'))->toBeTrue();
    expect($this->regularUser->hasRole('user manager'))->toBeTrue();
    
    // Change role from editor to viewer
    $response = $this->actingAs($this->adminUser)
        ->post(route('users.remove-role', ['tenant' => $this->tenant, 'user' => $this->regularUser->id]), [
            'role' => 'user'
        ]);
    
    $response->assertStatus(302); // Redirect on success
    
    // Check if the role was removed
    $this->regularUser->refresh();
    expect($this->regularUser->hasRole('user'))->toBeFalse();
    expect($this->regularUser->hasRole('user manager'))->toBeTrue();
});

it('validates role assignments [002]', function () {
    $this->actingAs($this->adminUser);
    // Try to assign a non-existent role
    
    $response = $this->put(route('users.assign-role', ['tenant' => $this->tenant, 'user' => $this->regularUser]), [
            'roles' => 'nonExistentRoleId'
        ]);
    
    $response->assertStatus(405); // Validation error
    
    // Check that no role was assigned
    $this->regularUser->refresh();
    expect($this->regularUser->roles->count())->toBe(0);
});

it('prevents unauthorized role changes [003]', function () {
    $this->actingAs($this->UserManager);
    $this->assertAuthenticated();

    // User Manager assigns Admin role to regular user
    $response = $this->post(route('users.assign-role', ['tenant' => $this->tenant, 'user' => $this->regularUser]), [
            'role' => 'admin'
        ]);
    $response->assertStatus(403); // Redirect on success
    
    // Check if the role was assigned
    $this->regularUser->refresh();
    expect($this->regularUser->hasRole('user'))->toBeTrue();
    expect($this->regularUser->hasRole('admin'))->toBeFalse();
});

it('prevents permission escalation [004]', function () {
    $this->actingAs($this->userManager);
    // Manager tries to give themselves admin role
    $response = $this->put(route('users.assign-role', ['tenant' => $this->tenant, 'user' => $this->regularUser]), [
            'roles' => 'admin'
        ]);
    
    $response->assertStatus(403); // Forbidden
    
    // Check that the role wasn't changed
    $this->userManager->refresh();
    expect($this->userManager->hasRole('admin'))->toBeFalse();
    expect($this->userManager->hasRole('user manager'))->toBeTrue();
    
    // Even admin should not be able to assign a higher role to themselves
    $this->actingAs($this->adminUser);
    // Admin tries to give themselves admin role
    $response = $this->put(route('users.assign-role', ['tenant' => $this->tenant, 'user' => $this->regularUser]), [
            'roles' => 'owner'
        ]);
    
    $response->assertStatus(403); // Forbidden
    
    // Check that the role wasn't changed
    $this->adminUser->refresh();
    expect($this->adminUser->hasRole('admin'))->toBeTrue();
    expect($this->adminUser->hasRole('owner'))->toBeTrue();
});

it('enforces permissions for all actions [005]', function () {
    // Test that permissions are enforced through role assignment
    
    // regular User can view users
    $this->actingAs($this->regularUser)
        ->get(route('users.index'))
        ->assertStatus(200);
    
    // Viewer cannot create users
    $this->actingAs($this->regularUser)
        ->get(route('users.create'))
        ->assertStatus(403);
    
    // User Manager can edit users
    $this->actingAs($this->userManager)
        ->get(route('users.edit', $this->regularUser->id))
        ->assertStatus(200);
    
    // Editor cannot delete users
    $this->actingAs($this->editorUser)
        ->delete(route('users.destroy', $this->regularUser->id))
        ->assertStatus(403);
    
    // Owner can perform all actions
    $this->actingAs($this->ownerUser)
        ->get(route('users.index'))
        ->assertStatus(200);
        
    $this->actingAs($this->ownerUser)
        ->get(route('users.create'))
        ->assertStatus(200);
        
    $this->actingAs($this->ownerUser)
        ->get(route('users.edit', $this->regularUser->id))
        ->assertStatus(200);
});

it('[permission_enforcement_validation] validates permission checks for all endpoints', function () {
    // Verify that permission middleware is applied to all endpoints
    $routes = [
        'users.index' => ['get', [], 'view users'],
        'users.create' => ['get', [], 'create users'],
        'users.edit' => ['get', ['id' => 1], 'edit users'],
        'users.store' => ['post', ['name' => 'Test', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password'], 'create users'],
        'users.update' => ['put', ['name' => 'Test', 'email' => 'test@example.com'], 'edit users'],
        'users.destroy' => ['delete', [], 'delete users'],
    ];
    
    // Create a user with no permissions
    $unprivilegedUser = User::factory()->create([
        'email' => 'unprivileged@example.com',
    ]);
    
    foreach ($routes as $route => $details) {
        [$method, $params, $permission] = $details;
        
        // Route parameters
        $routeParams = [];
        if ($route === 'users.edit' || $route === 'users.update' || $route === 'users.destroy') {
            $routeParams = [$this->regularUser->id];
        }
        
        // Test without permission
        $this->actingAs($unprivilegedUser)
            ->$method(route($route, $routeParams), $params)
            ->assertStatus(403);
        
        // Give the user this specific permission
        $unprivilegedUser->givePermissionTo($permission);
        
        // Test with permission
        $response = $this->actingAs($unprivilegedUser)
            ->$method(route($route, $routeParams), $params);
        
        // Should now be allowed (200 or 302 for successful redirects)
        expect(in_array($response->status(), [200, 302]))->toBeTrue();
        
        // Remove the permission for the next test
        $unprivilegedUser->revokePermissionTo($permission);
    }
});

it('[seeding_integrity_happy_path] ensures basic roles and permissions are seeded correctly', function () {
    // We assume seeding has been done in the beforeEach
    
    // Check that basic roles exist
    expect(Role::where('name', 'admin')->exists())->toBeTrue();
    expect(Role::where('name', 'user manager')->exists())->toBeTrue();
    expect(Role::where('name', 'user')->exists())->toBeTrue();
    
    // Check that basic permissions exist
    expect(Permission::where('name', 'view users')->exists())->toBeTrue();
    expect(Permission::where('name', 'create users')->exists())->toBeTrue();
    expect(Permission::where('name', 'edit users')->exists())->toBeTrue();
    expect(Permission::where('name', 'delete users')->exists())->toBeTrue();
    
    // Check that permissions are assigned to roles correctly
    $adminRole = Role::where('name', 'admin')->first();
    expect($adminRole->hasPermissionTo('view users'))->toBeTrue();
    expect($adminRole->hasPermissionTo('create users'))->toBeTrue();
    expect($adminRole->hasPermissionTo('edit users'))->toBeTrue();
    expect($adminRole->hasPermissionTo('delete users'))->toBeTrue();
    
    $editorRole = Role::where('name', 'user manager')->first();
    expect($editorRole->hasPermissionTo('view users'))->toBeTrue();
    expect($editorRole->hasPermissionTo('edit users'))->toBeTrue();
    expect($editorRole->hasPermissionTo('create users'))->toBeFalse();
    expect($editorRole->hasPermissionTo('delete users'))->toBeFalse();
    
    $viewerRole = Role::where('name', 'user')->first();
    expect($viewerRole->hasPermissionTo('view users'))->toBeTrue();
    expect($viewerRole->hasPermissionTo('edit users'))->toBeFalse();
    expect($viewerRole->hasPermissionTo('create users'))->toBeFalse();
    expect($viewerRole->hasPermissionTo('delete users'))->toBeFalse();
});