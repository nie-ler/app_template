<?php

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Tenant;
use Illuminate\Support\Facades\DB;

// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
    
    // Store tenants for the tests
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

    $this->artisan('migrate', [
        '--database' => 'tenant',
        '--path' => base_path('vendor/nilit/lara-boiler-core/database/migrations/tenant'),
    ])->run();

    expect(Schema::connection('tenant')->hasTable('tenant_audit_logs'))->toBeTrue();
    
    // Create a test model in the first tenant
    $testModel1 = \Nilit\LaraBoilerCore\Models\TestModel::create([
        'name' => 'Test Model in Tenant 1',
    ]);


    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Create a test model in the second tenant with the same name
    $testModel2 = \Nilit\LaraBoilerCore\Models\TestModel::create([
        'name' => 'Test Model in Tenant 2',
    ]);
    
    // Verify the models exist in their respective tenants
    $this->assertDatabaseHas('test_table', [
        'name' => 'Test Model in Tenant 2',
    ]);
    
    // Switch back to first tenant and check
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    $this->assertDatabaseHas('test_table', [
        'name' => 'Test Model in Tenant 1',
    ]);
    
    $this->assertDatabaseMissing('test_table', [
        'name' => 'Test Model in Tenant 2',
    ]);
});

it('[multi_tenancy_db_separation_validation] scopes data queries to the correct tenant database', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Create multiple test models in the first tenant
    for ($i = 1; $i <= 5; $i++) {
        \Nilit\LaraBoilerCore\Models\TestModel::create([
            'name' => "Tenant 1 Model $i",
        ]);
    }
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Create different test models in the second tenant
    for ($i = 1; $i <= 3; $i++) {
        \Nilit\LaraBoilerCore\Models\TestModel::create([
            'name' => "Tenant 2 Model $i",
        ]);
    }
    
    // Count should be only for second tenant
    expect(\Nilit\LaraBoilerCore\Models\TestModel::count())->toBe(3);
    
    // Check specific model exists in current tenant
    expect(\Nilit\LaraBoilerCore\Models\TestModel::where('name', 'Tenant 2 Model 1')->exists())->toBeTrue();
    
    // Model from first tenant should not exist in second tenant
    expect(\Nilit\LaraBoilerCore\Models\TestModel::where('name', 'Tenant 1 Model 1')->exists())->toBeFalse();
    
    // Switch back to first tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    // Count should be only for first tenant
    expect(\Nilit\LaraBoilerCore\Models\TestModel::count())->toBe(5);
    
    // Check specific model exists in current tenant
    expect(\Nilit\LaraBoilerCore\Models\TestModel::where('name', 'Tenant 1 Model 1')->exists())->toBeTrue();
    
    // Model from second tenant should not exist in first tenant
    expect(\Nilit\LaraBoilerCore\Models\TestModel::where('name', 'Tenant 2 Model 1')->exists())->toBeFalse();
});

it('[multi_tenancy_context_switch_happy_path] switches context between tenants correctly', function () {
    // Initialize first tenant and create a user
    tenancy()->initialize($this->tenant1);
    
    $user1 = User::factory()->create([
        'name' => 'User in Tenant 1',
        'email' => 'user1@example.com',
    ]);
    
    expect(tenancy()->getTenant()->id)->toBe($this->tenant1->id);
    expect(User::count())->toBe(1);
    
    // Switch to second tenant and create a different user
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    $user2 = User::factory()->create([
        'name' => 'User in Tenant 2',
        'email' => 'user2@example.com',
    ]);
    
    expect(tenancy()->getTenant()->id)->toBe($this->tenant2->id);
    expect(User::count())->toBe(1);
    
    // Switch back to first tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    expect(tenancy()->getTenant()->id)->toBe($this->tenant1->id);
    expect(User::count())->toBe(1);
    expect(User::first()->name)->toBe('User in Tenant 1');
});

it('[multi_tenancy_context_switch_edge_invalid_tenant] handles invalid tenant switches', function () {
    // Try to initialize with a non-existent tenant ID
    try {
        tenancy()->initialize('non-existent-tenant-id');
        $this->fail('Expected an exception when switching to a non-existent tenant');
    } catch (\Exception $e) {
        // Exception should be thrown
        expect($e)->toBeInstanceOf(\Exception::class);
    }
    
    // After failed initialization, tenant context should be null
    expect(tenancy()->getTenant())->toBeNull();
    
    // Try to initialize with a deleted tenant
    $deletedTenant = Tenant::create([
        'id' => 'tenant-to-delete-' . uniqid(),
        'name' => 'Tenant To Delete',
    ]);
    
    $deletedTenantId = $deletedTenant->id;
    $deletedTenant->delete();
    
    try {
        tenancy()->initialize($deletedTenantId);
        $this->fail('Expected an exception when switching to a deleted tenant');
    } catch (\Exception $e) {
        // Exception should be thrown
        expect($e)->toBeInstanceOf(\Exception::class);
    }
});

it('[multi_tenancy_isolation_enforcement_happy_path] prevents data leakage between tenants', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);
    
    // Create a user in the first tenant
    $user1 = User::factory()->create([
        'name' => 'User in Tenant 1',
        'email' => 'isolation@example.com', // Same email in both tenants
    ]);
    
    // Create a unique test model
    \Nilit\LaraBoilerCore\Models\TestModel::create([
        'name' => 'Unique Test Model',
    ]);
    
    // Switch to second tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Create a user with the same email in the second tenant
    $user2 = User::factory()->create([
        'name' => 'User in Tenant 2',
        'email' => 'isolation@example.com', // Same email in both tenants
    ]);
    
    // Create another test model with the same name
    \Nilit\LaraBoilerCore\Models\TestModel::create([
        'name' => 'Unique Test Model',
    ]);
    
    // Both users should exist in their respective databases
    expect(User::where('email', 'isolation@example.com')->count())->toBe(1);
    expect(\Nilit\LaraBoilerCore\Models\TestModel::where('name', 'Unique Test Model')->count())->toBe(1);
    
    // Switch back to first tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    // Check that the data is still isolated
    expect(User::where('email', 'isolation@example.com')->count())->toBe(1);
    expect(User::where('email', 'isolation@example.com')->first()->name)->toBe('User in Tenant 1');
    expect(\Nilit\LaraBoilerCore\Models\TestModel::where('name', 'Unique Test Model')->count())->toBe(1);
});

it('[multi_tenancy_isolation_enforcement_edge_leakage] detects and blocks attempted data leakage', function () {
    // Initialize first tenant and create a test model
    tenancy()->initialize($this->tenant1);
    
    $testModel1 = \Nilit\LaraBoilerCore\Models\TestModel::create([
        'name' => 'Leakage Test Model',
    ]);
    $testModel1Id = $testModel1->id;
    
    // Try to access this model from a different tenant
    tenancy()->end();
    tenancy()->initialize($this->tenant2);
    
    // Directly accessing by ID should return null
    expect(\Nilit\LaraBoilerCore\Models\TestModel::find($testModel1Id))->toBeNull();
    
    // Attempting to update data from another tenant via direct SQL (simulating leakage)
    $affected = DB::table('test_table')
        ->where('id', $testModel1Id)
        ->update(['name' => 'Attempted Leakage']);
    
    // Should not affect any rows because the model doesn't exist in this tenant
    expect($affected)->toBe(0);
    
    // Switch back to first tenant and verify data was not affected
    tenancy()->end();
    tenancy()->initialize($this->tenant1);
    
    $testModel1Fresh = \Nilit\LaraBoilerCore\Models\TestModel::find($testModel1Id);
    expect($testModel1Fresh)->not->toBeNull();
    expect($testModel1Fresh->name)->toBe('Leakage Test Model');
});
