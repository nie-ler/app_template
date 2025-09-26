<?php

use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\User;

beforeEach(function () {
    // Create tenant for testing
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Create test user
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
    
    $this->user = $user;
    $this->tenant = $tenant;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Tenant Context Management Tests
it('[tenant_context_initialization_happy_path] initializes tenant context correctly', function () {
    // End tenancy to start fresh
    tenancy()->end();
    
    // Initialize the tenant context
    tenancy()->initialize($this->tenant);
    
    // Check that the tenant context is correctly set
    expect(tenancy()->getTenant())->not->toBeNull();
    expect(tenancy()->getTenant()->id)->toBe($this->tenant->id);
    
    // Check that the tenant database connection is set
    expect(DB::connection()->getDatabaseName())->toBe($this->tenant->id);
    
    // Check that we can access tenant-specific models
    $user = User::find($this->user->id);
    expect($user)->not->toBeNull();
    expect($user->tenant_id)->toBe($this->tenant->id);
});

it('[tenant_context_persistence] maintains tenant context throughout the request lifecycle', function () {
    // Simulate a request lifecycle with multiple database operations
    
    // Start with user creation
    $newUser = User::factory()->create([
        'name' => 'Persistence Test User',
        'email' => 'persistence@example.com',
    ]);
    
    // Perform another database operation
    $updatedUser = User::find($newUser->id);
    $updatedUser->name = 'Updated Name';
    $updatedUser->save();
    
    // Simulate a complex query that might switch connections
    $result = DB::table('users')
        ->join('tenant_roles_users', 'users.id', '=', 'tenant_roles_users.user_id')
        ->select('users.*')
        ->where('users.id', $newUser->id)
        ->get();
    
    // Verify the tenant context is still correct
    expect(tenancy()->getTenant()->id)->toBe($this->tenant->id);
    
    // Verify we can still access our user
    $finalUser = User::find($newUser->id);
    expect($finalUser)->not->toBeNull();
    expect($finalUser->name)->toBe('Updated Name');
});

it('[tenant_context_middleware_enforcement] enforces tenant context in middleware', function () {
    // End current tenancy
    tenancy()->end();
    
    // Create a request to a tenant route without setting tenant context
    $response = $this->get('/tenant/dashboard');
    
    // Should redirect to a "select tenant" page or return an error
    $response->assertStatus(302); // Redirect
    
    // Now set the tenant in the session and try again
    $response = $this->withSession(['tenant_id' => $this->tenant->id])
        ->get('/tenant/dashboard');
    
    // Should work now or at least not redirect for the same reason
    expect($response->status())->not->toBe(302);
});

it('[tenant_context_switching_happy_path] switches between tenants programmatically', function () {
    // Create a second tenant
    $secondTenant = Tenant::create([
        'id' => 'second-tenant-' . uniqid(),
        'name' => 'Second Tenant',
    ]);
    
    // Initial tenant is set
    expect(tenancy()->getTenant()->id)->toBe($this->tenant->id);
    
    // Create a user in the first tenant
    $user1 = User::factory()->create([
        'name' => 'First Tenant User',
        'email' => 'first@example.com',
    ]);
    
    // Switch to the second tenant
    tenancy()->end();
    tenancy()->initialize($secondTenant);
    
    // Confirm we've switched
    expect(tenancy()->getTenant()->id)->toBe($secondTenant->id);
    
    // Create a user in the second tenant
    $user2 = User::factory()->create([
        'name' => 'Second Tenant User',
        'email' => 'second@example.com',
    ]);
    
    // User from first tenant should not exist here
    expect(User::where('email', 'first@example.com')->exists())->toBeFalse();
    
    // Switch back to first tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant);
    
    // Confirm we're back
    expect(tenancy()->getTenant()->id)->toBe($this->tenant->id);
    
    // User from second tenant should not exist here
    expect(User::where('email', 'second@example.com')->exists())->toBeFalse();
    
    // But our original user should
    expect(User::where('email', 'first@example.com')->exists())->toBeTrue();
});

it('[tenant_context_switching_permission_denied] blocks unauthorized tenant switching', function () {
    // Create a second tenant
    $secondTenant = Tenant::create([
        'id' => 'second-tenant-' . uniqid(),
        'name' => 'Second Tenant',
    ]);
    
    // Create a user that belongs only to the first tenant
    $restrictedUser = User::factory()->create([
        'name' => 'Restricted User',
        'email' => 'restricted@example.com',
    ]);
    
    // Attempt to switch tenant context via the API as this restricted user
    $this->actingAs($restrictedUser)
        ->post('/api/switch-tenant', [
            'tenant_id' => $secondTenant->id
        ])
        ->assertStatus(403); // Forbidden
    
    // Verify tenant wasn't switched in the session
    $this->assertSessionMissing('tenant_id', $secondTenant->id);
});

it('[tenant_context_edge_deleted_tenant] handles deleted tenant gracefully', function () {
    // Create a tenant to be deleted
    $deletedTenant = Tenant::create([
        'id' => 'deleted-tenant-' . uniqid(),
        'name' => 'Deleted Tenant',
    ]);
    
    $deletedTenantId = $deletedTenant->id;
    
    // Initialize it to ensure it works
    tenancy()->end();
    tenancy()->initialize($deletedTenant);
    
    // End tenancy and delete the tenant
    tenancy()->end();
    $deletedTenant->delete();
    
    // Try to initialize the deleted tenant
    try {
        tenancy()->initialize($deletedTenantId);
        $this->fail('Should not be able to initialize a deleted tenant');
    } catch (\Exception $e) {
        // Expected exception
        expect($e)->toBeInstanceOf(\Exception::class);
    }
    
    // Request a page with the deleted tenant in the session
    $response = $this->withSession(['tenant_id' => $deletedTenantId])
        ->get('/tenant/dashboard');
    
    // Should be redirected to tenant selection or error page
    $response->assertStatus(302);
});
