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
    
    // Create a user to be deleted
    $userToDelete = User::factory()->create([
        'email' => 'delete.me@example.com',
        'name' => 'Delete Me',
    ]);
    
    // Make these users available to the tests
    $this->adminUser = $adminUser;
    $this->regularUser = $regularUser;
    $this->userToDelete = $userToDelete;
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

// User Deletion Tests
it('[user_deletion_happy_path] allows admin to delete a user', function () {
    $response = $this->actingAs($this->adminUser)
        ->delete(route('users.destroy', $this->userToDelete->id));
        
    $response->assertStatus(302); // Redirected after successful deletion
    $response->assertRedirect(route('users.index'));
    
    // Check that the user was deleted from the database
    $this->assertDatabaseMissing('users', [
        'id' => $this->userToDelete->id,
        'deleted_at' => null,
    ]);
    
    // Check if the deletion was logged
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.deleted',
        'subject_id' => $this->userToDelete->id,
    ]);
});

it('[user_deletion_validation_non_existent_user] returns 404 for non-existent user', function () {
    $nonExistentId = User::max('id') + 1000;
    
    $response = $this->actingAs($this->adminUser)
        ->delete(route('users.destroy', $nonExistentId));
        
    $response->assertStatus(404); // Not found
});

it('[user_deletion_permission_denied] prevents unauthorized user deletion', function () {
    $response = $this->actingAs($this->regularUser)
        ->delete(route('users.destroy', $this->userToDelete->id));
        
    $response->assertStatus(403); // Forbidden
    
    // Verify user still exists
    $this->assertDatabaseHas('users', [
        'id' => $this->userToDelete->id,
        'deleted_at' => null,
    ]);
});

it('[user_deletion_edge_self_deletion] prevents users from deleting themselves', function () {
    $response = $this->actingAs($this->adminUser)
        ->delete(route('users.destroy', $this->adminUser->id));
        
    $response->assertStatus(422); // Unprocessable Entity
    $response->assertSessionHasErrors(['user' => 'You cannot delete your own account.']);
    
    // Verify admin user still exists
    $this->assertDatabaseHas('users', [
        'id' => $this->adminUser->id,
        'deleted_at' => null,
    ]);
});
