<?php

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Tenant;

beforeEach(function () {
    // Create two tenants for testing
    $tenant1 = Tenant::create([
        'id' => 'test-tenant-1-' . uniqid(),
        'name' => 'Test Tenant 1',
    ]);
    
    $tenant2 = Tenant::create([
        'id' => 'test-tenant-2-' . uniqid(),
        'name' => 'Test Tenant 2',
    ]);
    
    // Configure tenancy for the first tenant
    tenancy()->initialize($tenant1);
    
    // Create admin user in first tenant
    $adminUser1 = User::factory()->create([
        'email' => 'admin1@example.com',
        'name' => 'Admin User 1',
    ]);
    
    $adminRole = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $viewUsersPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'view users']);
    
    $adminRole->givePermissionTo([
        $viewUsersPermission,
    ]);
    
    $adminUser1->assignRole($adminRole);
    
    // Create regular user in first tenant
    $regularUser1 = User::factory()->create([
        'email' => 'user1@example.com',
        'name' => 'Regular User 1',
    ]);
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($tenant2);
    
    // Create admin user in second tenant
    $adminUser2 = User::factory()->create([
        'email' => 'admin2@example.com',
        'name' => 'Admin User 2',
    ]);
    
    $adminRole2 = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $viewUsersPermission2 = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'view users']);
    
    $adminRole2->givePermissionTo([
        $viewUsersPermission2,
    ]);
    
    $adminUser2->assignRole($adminRole2);
    
    // Create regular user in second tenant
    $regularUser2 = User::factory()->create([
        'email' => 'user2@example.com',
        'name' => 'Regular User 2',
    ]);
    
    // Store all entities for the tests
    $this->tenant1 = $tenant1;
    $this->tenant2 = $tenant2;
    $this->adminUser1 = $adminUser1;
    $this->regularUser1 = $regularUser1;
    $this->adminUser2 = $adminUser2;
    $this->regularUser2 = $regularUser2;
    
    // Reset tenancy for tests
    tenancy()->end();
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Tenant Isolation Tests
it('[tenant_isolation_happy_path] ensures users can only access their own tenant data', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Admin from tenant 1 should see users from tenant 1 only
    $response = $this->actingAs($this->adminUser1)
        ->get(route('users.index'));
    
    $response->assertStatus(200);
    $response->assertSee($this->adminUser1->name);
    $response->assertSee($this->regularUser1->name);
    $response->assertDontSee($this->adminUser2->name);
    $response->assertDontSee($this->regularUser2->name);
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Admin from tenant 2 should see users from tenant 2 only
    $response = $this->actingAs($this->adminUser2)
        ->get(route('users.index'));
    
    $response->assertStatus(200);
    $response->assertSee($this->adminUser2->name);
    $response->assertSee($this->regularUser2->name);
    $response->assertDontSee($this->adminUser1->name);
    $response->assertDontSee($this->regularUser1->name);
});

it('[tenant_isolation_validation] ensures all queries are scoped to tenant', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Count users in tenant 1
    $tenant1UserCount = User::count();
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Count users in tenant 2
    $tenant2UserCount = User::count();
    
    // Each tenant should have exactly 2 users
    expect($tenant1UserCount)->toBe(2);
    expect($tenant2UserCount)->toBe(2);
    
    // User IDs might be the same across tenants, but they should be different users
    $userFromTenant1 = $this->adminUser1;
    $userWithSameIdFromTenant2 = User::find($userFromTenant1->id);
    
    // If a user with the same ID exists in tenant 2, it should be a different user
    if ($userWithSameIdFromTenant2) {
        expect($userWithSameIdFromTenant2->email)->not->toBe($userFromTenant1->email);
    }
});

it('[tenant_isolation_error_cross_tenant] prevents cross-tenant access', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Admin from tenant 1 tries to access a user from tenant 2 (by ID)
    // This should fail with a 404 as the user doesn't exist in this tenant's context
    $response = $this->actingAs($this->adminUser1)
        ->get(route('users.edit', $this->adminUser2->id));
    
    $response->assertStatus(404);
    
    // Similarly, trying to update or delete a user from another tenant should fail
    $response = $this->actingAs($this->adminUser1)
        ->put(route('users.update', $this->adminUser2->id), [
            'name' => 'Attempted Cross-Tenant Update',
            'email' => 'should.not.work@example.com'
        ]);
    
    $response->assertStatus(404);
    
    $response = $this->actingAs($this->adminUser1)
        ->delete(route('users.destroy', $this->adminUser2->id));
    
    $response->assertStatus(404);
});

it('[tenant_isolation_edge_data_leakage] prevents data leakage between tenants', function () {
    // Initialize first tenant and create a user with a specific email
    tenancy()->initialize($this->tenant1);
    
    $uniqueEmail = 'unique.user@example.com';
    
    User::factory()->create([
        'email' => $uniqueEmail,
        'name' => 'Unique User in Tenant 1',
    ]);
    
    // Verify the user exists in tenant 1
    $this->assertDatabaseHas('users', [
        'email' => $uniqueEmail
    ]);
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Try to create a user with the same email in tenant 2 (should succeed)
    User::factory()->create([
        'email' => $uniqueEmail,
        'name' => 'Unique User in Tenant 2',
    ]);
    
    // Verify the user exists in tenant 2
    $this->assertDatabaseHas('users', [
        'email' => $uniqueEmail
    ]);
    
    // Query both users to ensure they have different IDs or other properties
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    $user1 = User::where('email', $uniqueEmail)->first();
    
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    $user2 = User::where('email', $uniqueEmail)->first();
    
    // Users should exist in both tenants but be completely separate entities
    expect($user1)->not->toBeNull();
    expect($user2)->not->toBeNull();
    expect(tenancy()->getTenant()->id)->toBe($this->tenant2->id);
    
    // The name should be different confirming these are different records
    expect($user2->name)->toBe('Unique User in Tenant 2');
});
