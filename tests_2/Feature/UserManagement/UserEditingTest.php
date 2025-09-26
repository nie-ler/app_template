<?php

use Nilit\LaraBoilerCore\Models\User;

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
        'name' => 'Admin User',
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
        'name' => 'Regular User',
    ]);
    
    // Create a user to be edited
    $userToEdit = User::factory()->create([
        'email' => 'edit.me@example.com',
        'name' => 'Edit Me',
    ]);
    
    // Make these users available to the tests
    $this->adminUser = $adminUser;
    $this->regularUser = $regularUser;
    $this->userToEdit = $userToEdit;
    $this->tenant = $tenant;
    
    // Create another tenant for cross-tenant tests
    $anotherTenant = \Nilit\LaraBoilerCore\Models\Tenant::create([
        'id' => 'another-tenant-' . uniqid(),
        'name' => 'Another Tenant',
    ]);
    $this->anotherTenant = $anotherTenant;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// User Editing Tests
it('[user_editing_happy_path] allows admin to edit user details', function () {
    $updatedData = [
        'name' => 'Updated Name',
        'email' => 'updated.email@example.com',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.update', $this->userToEdit->id), $updatedData);
        
    $response->assertStatus(302); // Redirected after successful update
    $response->assertRedirect(route('users.index'));
    
    // Check that the user was updated in the database
    $this->assertDatabaseHas('users', [
        'id' => $this->userToEdit->id,
        'name' => 'Updated Name',
        'email' => 'updated.email@example.com',
    ]);
    
    // Check if the activity was logged
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.updated',
        'subject_id' => $this->userToEdit->id,
    ]);
});

it('[user_editing_validation_invalid_email] validates email format', function () {
    $invalidData = [
        'name' => 'Valid Name',
        'email' => 'not-a-valid-email',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.update', $this->userToEdit->id), $invalidData);
        
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['email']);
});

it('[user_editing_validation_missing_fields] validates required fields', function () {
    $invalidData = [
        'name' => '',
        'email' => '',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.update', $this->userToEdit->id), $invalidData);
        
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['name', 'email']);
});

it('[user_editing_permission_denied] prevents unauthorized user editing', function () {
    $updatedData = [
        'name' => 'Updated Name',
        'email' => 'updated.email@example.com',
    ];
    
    $response = $this->actingAs($this->regularUser)
        ->put(route('users.update', $this->userToEdit->id), $updatedData);
        
    $response->assertStatus(403); // Forbidden
});

it('[user_editing_edge_cross_tenant] prevents editing user from another tenant', function () {
    // Initialize the other tenant and create a user there
    tenancy()->initialize($this->anotherTenant);
    $otherTenantUser = User::factory()->create([
        'email' => 'other.tenant@example.com',
        'name' => 'Other Tenant User',
    ]);
    $otherTenantUserId = $otherTenantUser->id;
    
    // Switch back to the original tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant);
    
    // Try to edit the user from the other tenant
    $updatedData = [
        'name' => 'Should Not Update',
        'email' => 'should.not.update@example.com',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.update', $otherTenantUserId), $updatedData);
        
    $response->assertStatus(404); // Not found
});

it('[user_editing_edge_non_existent_user] returns 404 for non-existent user', function () {
    $nonExistentId = User::max('id') + 1000;
    
    $updatedData = [
        'name' => 'Updated Name',
        'email' => 'updated.email@example.com',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->put(route('users.update', $nonExistentId), $updatedData);
        
    $response->assertStatus(404); // Not found
});
