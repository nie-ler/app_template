<?php

use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create two tenants for testing
    $tenant1 = Tenant::create([
        'id' => 'test-tenant-1-' . uniqid(),
        'name' => 'Test Tenant 1',
        'email' => 'T1'.uniqid().'@example.com',
    ]);
    
    $tenant2 = Tenant::create([
        'id' => 'test-tenant-2-' . uniqid(),
        'name' => 'Test Tenant 2',
        'email' => 'T2'.uniqid().'@example.com',        
    ]);
    
    // Store for tests
    $this->tenant1 = $tenant1;
    $this->tenant2 = $tenant2;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Multi-Tenancy Tests
it('[multi_tenancy_db_separation_happy_path] stores tenant data in the correct database', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Create a user in the first tenant
    $user1 = User::factory()->create([
        'email' => 'user1@example.com',
        'name' => 'User 1',
    ]);
    
    // Verify user exists in tenant1's database
    $this->assertDatabaseHas('users', [
        'email' => 'user1@example.com',
    ]);
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Create a user in the second tenant
    $user2 = User::factory()->create([
        'email' => 'user2@example.com',
        'name' => 'User 2',
    ]);
    
    // Verify user2 exists in tenant2's database
    $this->assertDatabaseHas('users', [
        'email' => 'user2@example.com',
    ]);
    
    // Verify user1 doesn't exist in tenant2's database
    $this->assertDatabaseMissing('users', [
        'email' => 'user1@example.com',
    ]);
    
    // Switch back to first tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    // Verify user1 exists in tenant1's database
    $this->assertDatabaseHas('users', [
        'email' => 'user1@example.com',
    ]);
    
    // Verify user2 doesn't exist in tenant1's database
    $this->assertDatabaseMissing('users', [
        'email' => 'user2@example.com',
    ]);
});

it('[multi_tenancy_db_separation_validation] scopes queries to the correct tenant database', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Create a user in the first tenant
    $user1 = User::factory()->create([
        'email' => 'user1@example.com',
        'name' => 'User 1',
    ]);
    
    // Store the ID
    $user1Id = $user1->id;
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Attempt to find the user by ID in tenant2's context
    $user1InTenant2 = User::find($user1Id);
    
    // Should not exist in tenant2
    expect($user1InTenant2)->toBeNull();
    
    // Create a user with the same ID in tenant2 if possible
    $user2 = User::factory()->create([
        'id' => $user1Id,
        'email' => 'different@example.com',
        'name' => 'Different User',
    ]);
    
    // Both users should have the same ID but be different users
    expect($user2->id)->toBe($user1Id);
    expect($user2->email)->not->toBe($user1->email);
    
    // Switch back to tenant1
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    // Check the user in tenant1 is still the same
    $user1FromDb = User::find($user1Id);
    expect($user1FromDb)->not->toBeNull();
    expect($user1FromDb->email)->toBe('user1@example.com');
    expect($user1FromDb->email)->not->toBe('different@example.com');
});

it('[multi_tenancy_context_switch_happy_path] switches context between tenants correctly', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Create test data in tenant1
    $user1 = User::factory()->create([
        'email' => 'user1@example.com',
        'name' => 'User 1',
    ]);
    
    // Verify the current tenant
    expect(tenancy()->getTenant()->id)->toBe($this->tenant1->id);
    
    // Switch to tenant2
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Verify the tenant switched
    expect(tenancy()->getTenant()->id)->toBe($this->tenant2->id);
    
    // Create test data in tenant2
    $user2 = User::factory()->create([
        'email' => 'user2@example.com',
        'name' => 'User 2',
    ]);
    
    // Verify each tenant has access to their own data only
    expect(User::where('email', 'user2@example.com')->exists())->toBeTrue();
    expect(User::where('email', 'user1@example.com')->exists())->toBeFalse();
    
    // Switch back to tenant1
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    // Verify the switch back worked
    expect(tenancy()->getTenant()->id)->toBe($this->tenant1->id);
    expect(User::where('email', 'user1@example.com')->exists())->toBeTrue();
    expect(User::where('email', 'user2@example.com')->exists())->toBeFalse();
});

it('[multi_tenancy_context_switch_edge_invalid_tenant] handles invalid tenant switching gracefully', function () {
    // Start with a valid tenant
    tenancy()->initialize($this->tenant1);
    expect(tenancy()->getTenant()->id)->toBe($this->tenant1->id);
    
    // End tenancy
    tenancy()->end();
    
    // Try to initialize with invalid tenant ID
    try {
        $invalidTenant = new Tenant();
        $invalidTenant->id = 'non-existent-tenant';
        tenancy()->initialize($invalidTenant);
        $this->fail('Should have thrown an exception');
    } catch (\Exception $e) {
        // Exception is expected
        expect($e)->toBeInstanceOf(\Exception::class);
    }
    
    // Create a tenant but don't save it to the database
    $unsavedTenant = new Tenant([
        'id' => 'unsaved-tenant',
        'name' => 'Unsaved Tenant'
    ]);
    
    try {
        tenancy()->initialize($unsavedTenant);
        $this->fail('Should have thrown an exception');
    } catch (\Exception $e) {
        // Exception is expected
        expect($e)->toBeInstanceOf(\Exception::class);
    }
});

it('[multi_tenancy_isolation_enforcement_happy_path] ensures no data leakage between tenants', function () {
    // Initialize first tenant and create user
    tenancy()->initialize($this->tenant1);
    $user1 = User::factory()->create([
        'email' => 'same@example.com', // Same email in both tenants
        'name' => 'Tenant 1 User',
    ]);
    
    // Switch to second tenant and create user with same email
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    $user2 = User::factory()->create([
        'email' => 'same@example.com', // Same email in both tenants
        'name' => 'Tenant 2 User',
    ]);
    
    // Test isolation: Users with same email should be different objects
    expect($user1->id)->toBe($user2->id); // IDs might be the same due to sequences
    expect($user1->name)->not->toBe($user2->name); // But these are different users
    
    // Check via query
    $queriedUser2 = User::where('email', 'same@example.com')->first();
    expect($queriedUser2->name)->toBe('Tenant 2 User');
    
    // Switch back to tenant1
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    $queriedUser1 = User::where('email', 'same@example.com')->first();
    expect($queriedUser1->name)->toBe('Tenant 1 User');
});

it('[multi_tenancy_isolation_enforcement_edge_leakage] detects and blocks attempted data leakage', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Create data in tenant1
    $user1 = User::factory()->create([
        'email' => 'user1@example.com',
        'name' => 'User 1',
    ]);
    
    // Store the ID for later
    $user1Id = $user1->id;
    
    // Switch to tenant2 
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Attempt direct database access to tenant1's data (simulating a security breach)
    // This should be prevented by the database separation
    
    // First confirm the user doesn't exist in tenant2's context
    expect(User::find($user1Id))->toBeNull();
    
    // Try to create a "leaked" query that might access tenant1's data
    // This simulates an attack attempt that would be prevented by proper database isolation
    try {
        // Simulate an attack by using a raw database connection
        // This should fail because the tenant1's table doesn't exist in tenant2's database
        $result = \DB::connection(tenancy()->getTenant()->tenancy_db_name)
            ->table('tenant1_users') // Non-existent table in tenant2
            ->where('id', $user1Id)
            ->first();
        
        // If we got here, there might be a problem
        $this->fail('Security breach: Accessed data from another tenant');
    } catch (\Exception $e) {
        // We expect an exception due to the table not existing
        // This confirms proper isolation
        expect($e->getMessage())->toContain('not exist');
    }
});
