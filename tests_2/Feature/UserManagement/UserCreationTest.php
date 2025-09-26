<?php

use Nilit\LaraBoilerCore\Models\User;
use Illuminate\Support\Facades\Hash;

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

// User Creation Tests
it('[user_creation_happy_path] allows admin to create new user', function () {
    $userData = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->post(route('users.store'), $userData);
        
    $response->assertStatus(302); // Redirected after successful creation
    $response->assertRedirect(route('users.index'));
    
    // Check that the user was created in the database
    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
    ]);
    
    // Check that the user was assigned to the correct tenant
    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser->tenant_id)->toBe($this->tenant->id);
});

it('[user_creation_validation_missing_fields] validates required fields', function () {
    $invalidData = [
        'name' => '',
        'email' => '',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->post(route('users.store'), $invalidData);
        
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['name', 'email']);
});

it('[user_creation_validation_duplicate_email] prevents duplicate email addresses', function () {
    $userData = [
        'name' => 'Duplicate Email',
        'email' => $this->regularUser->email, // Already exists
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->post(route('users.store'), $userData);
        
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['email']);
});

it('[user_creation_permission_denied] prevents unauthorized user creation', function () {
    $userData = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
    
    $response = $this->actingAs($this->regularUser)
        ->post(route('users.store'), $userData);
        
    $response->assertStatus(403); // Forbidden
});

it('[user_creation_edge_cross_tenant] prevents cross-tenant user creation', function () {
    // Initialize the other tenant
    tenancy()->initialize($this->anotherTenant);
    
    $userData = [
        'name' => 'Cross Tenant User',
        'email' => 'crossuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'tenant_id' => $this->tenant->id, // Trying to create for another tenant
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->post(route('users.store'), $userData);
        
    $response->assertStatus(422); // Validation error
});

it('[user_creation_edge_invalid_tenant] prevents assignment to invalid tenant', function () {
    $userData = [
        'name' => 'Invalid Tenant User',
        'email' => 'invalidtenant@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'tenant_id' => 'non-existent-tenant-id',
    ];
    
    $response = $this->actingAs($this->adminUser)
        ->post(route('users.store'), $userData);
        
    $response->assertStatus(422); // Validation error
});
