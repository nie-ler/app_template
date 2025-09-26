<?php

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\TenantRole;
use Nilit\LaraBoilerCore\Models\TenantPermission;
use Nilit\LaraBoilerCore\Models\Tenant;

beforeEach(function () {
    // Create a tenant for testing
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Create base permissions
    $viewUsersPermission = TenantPermission::create(['name' => 'view users']);
    $createUsersPermission = TenantPermission::create(['name' => 'create users']);
    $editUsersPermission = TenantPermission::create(['name' => 'edit users']);
    $deleteUsersPermission = TenantPermission::create(['name' => 'delete users']);
    $manageRolesPermission = TenantPermission::create(['name' => 'manage roles']);
    
    // Create roles
    $adminRole = TenantRole::create(['name' => 'admin']);
    $editorRole = TenantRole::create(['name' => 'editor']);
    $viewerRole = TenantRole::create(['name' => 'viewer']);
    
    // Assign permissions to roles
    $adminRole->givePermissionTo([
        $viewUsersPermission,
        $createUsersPermission,
        $editUsersPermission,
        $deleteUsersPermission,
        $manageRolesPermission
    ]);
    
    $editorRole->givePermissionTo([
        $viewUsersPermission,
        $editUsersPermission
    ]);
    
    $viewerRole->givePermissionTo([
        $viewUsersPermission
    ]);
    
    // Create users with different roles
    $adminUser = User::factory()->create([
        'email' => 'admin@example.com',
        'name' => 'Admin User',
    ]);
    $adminUser->assignRole($adminRole);
    
    $editorUser = User::factory()->create([
        'email' => 'editor@example.com',
        'name' => 'Editor User',
    ]);
    $editorUser->assignRole($editorRole);
    
    $viewerUser = User::factory()->create([
        'email' => 'viewer@example.com',
        'name' => 'Viewer User',
    ]);
    $viewerUser->assignRole($viewerRole);
    
    $regularUser = User::factory()->create([
        'email' => 'regular@example.com',
        'name' => 'Regular User',
    ]);
    
    // Make these users available to the tests
    $this->adminUser = $adminUser;
    $this->editorUser = $editorUser;
    $this->viewerUser = $viewerUser;
    $this->regularUser = $regularUser;
    $this->tenant = $tenant;
    
    // Store permission and role objects for tests
    $this->adminRole = $adminRole;
    $this->editorRole = $editorRole;
    $this->viewerRole = $viewerRole;
    $this->permissions = [
        'view_users' => $viewUsersPermission,
        'create_users' => $createUsersPermission,
        'edit_users' => $editUsersPermission,
        'delete_users' => $deleteUsersPermission,
        'manage_roles' => $manageRolesPermission,
    ];
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Role and Permission Management Tests
it('[role_assignment_happy_path] allows assigning and changing roles', function () {
    // Admin assigns editor role to regular user
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.roles.update', $this->regularUser->id), [
            'roles' => [$this->editorRole->id]
        ]);
    
    $response->assertStatus(302); // Redirect on success
    
    // Check if the role was assigned
    $this->regularUser->refresh();
    expect($this->regularUser->hasRole('editor'))->toBeTrue();
    
    // Change role from editor to viewer
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.roles.update', $this->regularUser->id), [
            'roles' => [$this->viewerRole->id]
        ]);
    
    $response->assertStatus(302); // Redirect on success
    
    // Check if the role was changed
    $this->regularUser->refresh();
    expect($this->regularUser->hasRole('editor'))->toBeFalse();
    expect($this->regularUser->hasRole('viewer'))->toBeTrue();
});

it('[role_assignment_validation] validates role assignments', function () {
    // Try to assign a non-existent role
    $nonExistentRoleId = TenantRole::max('id') + 1000;
    
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.roles.update', $this->regularUser->id), [
            'roles' => [$nonExistentRoleId]
        ]);
    
    $response->assertStatus(422); // Validation error
    
    // Check that no role was assigned
    $this->regularUser->refresh();
    expect($this->regularUser->roles->count())->toBe(0);
});

it('[role_assignment_permission_denied] prevents unauthorized role changes', function () {
    // Editor tries to assign admin role to regular user
    $response = $this->actingAs($this->editorUser)
        ->put(route('users.roles.update', $this->regularUser->id), [
            'roles' => [$this->adminRole->id]
        ]);
    
    $response->assertStatus(403); // Forbidden
    
    // Check that no role was assigned
    $this->regularUser->refresh();
    expect($this->regularUser->roles->count())->toBe(0);
});

it('[role_assignment_edge_escalation] prevents permission escalation', function () {
    // Editor tries to give themselves admin role
    $response = $this->actingAs($this->editorUser)
        ->put(route('users.roles.update', $this->editorUser->id), [
            'roles' => [$this->adminRole->id]
        ]);
    
    $response->assertStatus(403); // Forbidden
    
    // Check that the role wasn't changed
    $this->editorUser->refresh();
    expect($this->editorUser->hasRole('admin'))->toBeFalse();
    expect($this->editorUser->hasRole('editor'))->toBeTrue();
    
    // Even admin should not be able to assign a higher role to themselves
    // Create super-admin role
    $superAdminRole = TenantRole::create(['name' => 'super-admin']);
    $superAdminRole->givePermissionTo(TenantPermission::all());
    
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.roles.update', $this->adminUser->id), [
            'roles' => [$superAdminRole->id]
        ]);
    
    // This should be prevented by business logic
    $this->adminUser->refresh();
    expect($this->adminUser->hasRole('super-admin'))->toBeFalse();
});

it('[permission_enforcement_happy_path] enforces permissions for all actions', function () {
    // Test that permissions are enforced through role assignment
    
    // Viewer can view users
    $this->actingAs($this->viewerUser)
        ->get(route('users.index'))
        ->assertStatus(200);
    
    // Viewer cannot create users
    $this->actingAs($this->viewerUser)
        ->get(route('users.create'))
        ->assertStatus(403);
    
    // Editor can edit users
    $this->actingAs($this->editorUser)
        ->get(route('users.edit', $this->regularUser->id))
        ->assertStatus(200);
    
    // Editor cannot delete users
    $this->actingAs($this->editorUser)
        ->delete(route('users.destroy', $this->regularUser->id))
        ->assertStatus(403);
    
    // Admin can perform all actions
    $this->actingAs($this->adminUser)
        ->get(route('users.index'))
        ->assertStatus(200);
        
    $this->actingAs($this->adminUser)
        ->get(route('users.create'))
        ->assertStatus(200);
        
    $this->actingAs($this->adminUser)
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
    expect(TenantRole::where('name', 'admin')->exists())->toBeTrue();
    expect(TenantRole::where('name', 'editor')->exists())->toBeTrue();
    expect(TenantRole::where('name', 'viewer')->exists())->toBeTrue();
    
    // Check that basic permissions exist
    expect(TenantPermission::where('name', 'view users')->exists())->toBeTrue();
    expect(TenantPermission::where('name', 'create users')->exists())->toBeTrue();
    expect(TenantPermission::where('name', 'edit users')->exists())->toBeTrue();
    expect(TenantPermission::where('name', 'delete users')->exists())->toBeTrue();
    
    // Check that permissions are assigned to roles correctly
    $adminRole = TenantRole::where('name', 'admin')->first();
    expect($adminRole->hasPermissionTo('view users'))->toBeTrue();
    expect($adminRole->hasPermissionTo('create users'))->toBeTrue();
    expect($adminRole->hasPermissionTo('edit users'))->toBeTrue();
    expect($adminRole->hasPermissionTo('delete users'))->toBeTrue();
    
    $editorRole = TenantRole::where('name', 'editor')->first();
    expect($editorRole->hasPermissionTo('view users'))->toBeTrue();
    expect($editorRole->hasPermissionTo('edit users'))->toBeTrue();
    expect($editorRole->hasPermissionTo('create users'))->toBeFalse();
    expect($editorRole->hasPermissionTo('delete users'))->toBeFalse();
    
    $viewerRole = TenantRole::where('name', 'viewer')->first();
    expect($viewerRole->hasPermissionTo('view users'))->toBeTrue();
    expect($viewerRole->hasPermissionTo('edit users'))->toBeFalse();
    expect($viewerRole->hasPermissionTo('create users'))->toBeFalse();
    expect($viewerRole->hasPermissionTo('delete users'))->toBeFalse();
});

it('[seeding_integrity_edge_missing_roles] handles missing roles gracefully', function () {
    // Clean up all roles and permissions
    TenantRole::query()->delete();
    TenantPermission::query()->delete();
    
    // Run the seeder again
    $seeder = new \Nilit\LaraBoilerCore\Database\Seeders\RolesAndPermissionsSeeder();
    $seeder->run();
    
    // Check that roles and permissions were recreated
    expect(TenantRole::count())->toBeGreaterThan(0);
    expect(TenantPermission::count())->toBeGreaterThan(0);
    
    // Check specific roles were created
    expect(TenantRole::where('name', 'admin')->exists())->toBeTrue();
    
    // Check permissions were assigned
    $adminRole = TenantRole::where('name', 'admin')->first();
    expect($adminRole->permissions->count())->toBeGreaterThan(0);
});
