<?php

use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\TenantRole;
use Nilit\LaraBoilerCore\Models\TenantPermission;
use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Plan;
use Nilit\LaraBoilerCore\Models\PlanFeature;

beforeEach(function () {
    // Empty database setup - we'll test the seeding itself
});

// Seeding Tests
it('[seeding_basic_data_happy_path] seeds initial data correctly', function () {
    // Run the database seeders
    $this->seed(\Nilit\LaraBoilerCore\Database\Seeders\DatabaseSeeder::class);
    
    // Check that basic plans were seeded
    expect(Plan::count())->toBeGreaterThan(0);
    
    // Check that there's a free plan
    expect(Plan::where('name', 'Free')->exists())->toBeTrue();
    
    // Create a tenant to check role/permission seeding
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Initialize tenant
    tenancy()->initialize($tenant);
    
    // Check that basic roles were seeded
    expect(TenantRole::count())->toBeGreaterThan(0);
    expect(TenantRole::where('name', 'admin')->exists())->toBeTrue();
    
    // Check that basic permissions were seeded
    expect(TenantPermission::count())->toBeGreaterThan(0);
    expect(TenantPermission::where('name', 'view users')->exists())->toBeTrue();
    
    // Check that roles have the right permissions
    $adminRole = TenantRole::where('name', 'admin')->first();
    expect($adminRole->permissions->count())->toBeGreaterThan(0);
    
    // End tenancy
    tenancy()->end();
});

it('[seeding_basic_data_edge_missing_data] handles missing or duplicate data gracefully', function () {
    // First run the seeder normally
    $this->seed(\Nilit\LaraBoilerCore\Database\Seeders\DatabaseSeeder::class);
    
    // Create a tenant
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Initialize tenant
    tenancy()->initialize($tenant);
    
    // Count initial roles and permissions
    $initialRoleCount = TenantRole::count();
    $initialPermissionCount = TenantPermission::count();
    
    // Run the seeder again to test handling of duplicates
    $this->seed(\Nilit\LaraBoilerCore\Database\Seeders\RolesAndPermissionsSeeder::class);
    
    // Count should be the same (no duplicates)
    expect(TenantRole::count())->toBe($initialRoleCount);
    expect(TenantPermission::count())->toBe($initialPermissionCount);
    
    // Now delete a role and run the seeder again
    TenantRole::where('name', 'admin')->delete();
    expect(TenantRole::where('name', 'admin')->exists())->toBeFalse();
    
    // Run the seeder to recreate the missing role
    $this->seed(\Nilit\LaraBoilerCore\Database\Seeders\RolesAndPermissionsSeeder::class);
    
    // Check that the admin role exists again
    expect(TenantRole::where('name', 'admin')->exists())->toBeTrue();
    
    // End tenancy
    tenancy()->end();
});
