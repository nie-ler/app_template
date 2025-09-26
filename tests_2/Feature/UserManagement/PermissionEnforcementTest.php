<?php

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\TenantRole;
use Nilit\LaraBoilerCore\Models\TenantPermission;

beforeEach(function () {
    // Create a tenant for testing
    $tenant = \Nilit\LaraBoilerCore\Models\Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Create permissions
    $viewUsersPermission = TenantPermission::create(['name' => 'view users']);
    $createUsersPermission = TenantPermission::create(['name' => 'create users']);
    $editUsersPermission = TenantPermission::create(['name' => 'edit users']);
    $deleteUsersPermission = TenantPermission::create(['name' => 'delete users']);
    
    // Create roles
    $adminRole = TenantRole::create(['name' => 'admin']);
    $editorRole = TenantRole::create(['name' => 'editor']);
    $viewerRole = TenantRole::create(['name' => 'viewer']);
    
    // Assign permissions to roles
    $adminRole->givePermissionTo([
        $viewUsersPermission,
        $createUsersPermission,
        $editUsersPermission,
        $deleteUsersPermission
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
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Permission Enforcement Tests
it('[permission_enforcement_happy_path] enforces correct permissions for user actions', function () {
    // Admin can view users
    $this->actingAs($this->adminUser)
        ->get(route('users.index'))
        ->assertStatus(200);
    
    // Editor can view users
    $this->actingAs($this->editorUser)
        ->get(route('users.index'))
        ->assertStatus(200);
    
    // Viewer can view users
    $this->actingAs($this->viewerUser)
        ->get(route('users.index'))
        ->assertStatus(200);
    
    // Regular user cannot view users
    $this->actingAs($this->regularUser)
        ->get(route('users.index'))
        ->assertStatus(403);
    
    // Create a new user data
    $newUserData = [
        'name' => 'New User',
        'email' => 'new.user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password'
    ];
    
    // Admin can create users
    $this->actingAs($this->adminUser)
        ->post(route('users.store'), $newUserData)
        ->assertStatus(302); // Redirect on success
    
    // Editor cannot create users
    $newUserData['email'] = 'another.user@example.com';
    $this->actingAs($this->editorUser)
        ->post(route('users.store'), $newUserData)
        ->assertStatus(403);
        
    // Get a user to edit
    $userToEdit = User::where('email', 'regular@example.com')->first();
    $updateData = [
        'name' => 'Updated Name',
        'email' => 'regular@example.com'
    ];
    
    // Admin can edit users
    $this->actingAs($this->adminUser)
        ->put(route('users.update', $userToEdit->id), $updateData)
        ->assertStatus(302); // Redirect on success
    
    // Editor can edit users
    $updateData['name'] = 'Another Updated Name';
    $this->actingAs($this->editorUser)
        ->put(route('users.update', $userToEdit->id), $updateData)
        ->assertStatus(302); // Redirect on success
    
    // Viewer cannot edit users
    $updateData['name'] = 'Should Not Update';
    $this->actingAs($this->viewerUser)
        ->put(route('users.update', $userToEdit->id), $updateData)
        ->assertStatus(403);
    
    // Admin can delete users
    $userToDelete = User::where('email', 'regular@example.com')->first();
    $this->actingAs($this->adminUser)
        ->delete(route('users.destroy', $userToDelete->id))
        ->assertStatus(302); // Redirect on success
});

it('[permission_enforcement_validation] enforces permission checks for all endpoints', function () {
    $routes = [
        'users.index' => 'get',
        'users.create' => 'get',
        'users.edit' => 'get',
    ];
    
    $testUser = User::where('email', 'regular@example.com')->first();
    
    // Test each route with the regular user who has no permissions
    foreach ($routes as $route => $method) {
        $params = $route === 'users.edit' ? [$testUser->id] : [];
        
        $this->actingAs($this->regularUser)
            ->$method(route($route, $params))
            ->assertStatus(403);
    }
    
    // Create data
    $createData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password'
    ];
    
    // Update data
    $updateData = [
        'name' => 'Updated Name',
        'email' => 'test@example.com'
    ];
    
    // Test POST, PUT and DELETE routes
    $this->actingAs($this->regularUser)
        ->post(route('users.store'), $createData)
        ->assertStatus(403);
    
    $this->actingAs($this->regularUser)
        ->put(route('users.update', $testUser->id), $updateData)
        ->assertStatus(403);
    
    $this->actingAs($this->regularUser)
        ->delete(route('users.destroy', $testUser->id))
        ->assertStatus(403);
});

it('[permission_enforcement_error_unauthorized] returns 403 errors for unauthorized actions', function () {
    // Get a user to test with
    $testUser = User::where('email', 'regular@example.com')->first();
    
    // Viewer attempting to edit a user
    $this->actingAs($this->viewerUser)
        ->get(route('users.edit', $testUser->id))
        ->assertStatus(403);
    
    // Viewer attempting to delete a user
    $this->actingAs($this->viewerUser)
        ->delete(route('users.destroy', $testUser->id))
        ->assertStatus(403);
});

it('[permission_enforcement_edge_escalation] prevents permission escalation attempts', function () {
    // Create a new role with higher permissions
    $superAdminRole = TenantRole::create(['name' => 'super-admin']);
    
    // Editor tries to assign themselves the super-admin role
    $roleData = [
        'roles' => [$superAdminRole->id]
    ];
    
    $this->actingAs($this->editorUser)
        ->put(route('users.roles.update', $this->editorUser->id), $roleData)
        ->assertStatus(403);
    
    // Verify editor still has only the editor role
    $this->editorUser->refresh();
    expect($this->editorUser->roles->pluck('name')->toArray())->toBe(['editor']);
});
