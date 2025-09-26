<?php

use Nilit\LaraBoilerCore\Models\Plan;
use Nilit\LaraBoilerCore\Models\PlanFeature;
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
    
    // Create admin user with all permissions
    $adminUser = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    
    $adminRole = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $managePlansPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'manage plans']);
    
    $adminRole->givePermissionTo([
        $managePlansPermission,
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
    $anotherTenant = Tenant::create([
        'id' => 'another-tenant-' . uniqid(),
        'name' => 'Another Tenant',
    ]);
    $this->anotherTenant = $anotherTenant;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Plan CRUD Tests
it('[plan_crud_happy_path] allows creating, reading, updating, and deleting plans', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Create a new plan
    $planData = [
        'name' => 'Test Plan',
        'description' => 'A test plan with limited features',
        'price' => 19.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ];
    
    $response = $this->post(route('plans.store'), $planData);
    $response->assertStatus(302); // Redirect after creation
    
    // Check that the plan was created
    $this->assertDatabaseHas('plans', [
        'name' => 'Test Plan',
        'price' => 19.99,
    ]);
    
    // Get the created plan
    $plan = Plan::where('name', 'Test Plan')->first();
    
    // Read the plan
    $response = $this->get(route('plans.show', $plan->id));
    $response->assertStatus(200);
    $response->assertSee('Test Plan');
    
    // Update the plan
    $updatedData = [
        'name' => 'Updated Plan',
        'description' => 'An updated test plan',
        'price' => 29.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ];
    
    $response = $this->put(route('plans.update', $plan->id), $updatedData);
    $response->assertStatus(302); // Redirect after update
    
    // Check that the plan was updated
    $this->assertDatabaseHas('plans', [
        'id' => $plan->id,
        'name' => 'Updated Plan',
        'price' => 29.99,
    ]);
    
    // Delete the plan
    $response = $this->delete(route('plans.destroy', $plan->id));
    $response->assertStatus(302); // Redirect after deletion
    
    // Check that the plan was deleted (or soft-deleted)
    $this->assertSoftDeleted('plans', [
        'id' => $plan->id,
    ]);
});

it('[plan_crud_validation] validates plan data', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Create a plan with invalid data
    $invalidPlanData = [
        'name' => '', // Empty name (required)
        'description' => 'A test plan',
        'price' => 'not-a-number', // Invalid price
        'billing_period' => 'invalid-period', // Invalid billing period
    ];
    
    $response = $this->post(route('plans.store'), $invalidPlanData);
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['name', 'price', 'billing_period']);
    
    // Create a valid plan to test update validation
    $validPlanData = [
        'name' => 'Valid Plan',
        'description' => 'A valid test plan',
        'price' => 19.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ];
    
    $this->post(route('plans.store'), $validPlanData);
    $plan = Plan::where('name', 'Valid Plan')->first();
    
    // Update with invalid data
    $invalidUpdateData = [
        'name' => '', // Empty name (required)
        'price' => -10, // Negative price
    ];
    
    $response = $this->put(route('plans.update', $plan->id), $invalidUpdateData);
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['name', 'price']);
});

it('[plan_crud_permission_denied] prevents unauthorized users from managing plans', function () {
    // Test as regular user without permissions
    $this->actingAs($this->regularUser);
    
    // Try to create a plan
    $planData = [
        'name' => 'Unauthorized Plan',
        'description' => 'A plan created without permission',
        'price' => 19.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ];
    
    $response = $this->post(route('plans.store'), $planData);
    $response->assertStatus(403); // Forbidden
    
    // Create a plan as admin
    $this->actingAs($this->adminUser);
    $this->post(route('plans.store'), $planData);
    $plan = Plan::where('name', 'Unauthorized Plan')->first();
    
    // Try to update the plan as regular user
    $this->actingAs($this->regularUser);
    $updatedData = [
        'name' => 'Updated Unauthorized Plan',
        'price' => 29.99,
    ];
    
    $response = $this->put(route('plans.update', $plan->id), $updatedData);
    $response->assertStatus(403); // Forbidden
    
    // Try to delete the plan as regular user
    $response = $this->delete(route('plans.destroy', $plan->id));
    $response->assertStatus(403); // Forbidden
});

it('[plan_crud_edge_cross_tenant] ensures plans are tenant-specific', function () {
    // Initialize the first tenant
    tenancy()->initialize($this->tenant);
    
    // Create a plan in the first tenant as admin
    $this->actingAs($this->adminUser);
    $planData = [
        'name' => 'Tenant-Specific Plan',
        'description' => 'A plan for the first tenant',
        'price' => 19.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ];
    
    $this->post(route('plans.store'), $planData);
    $plan = Plan::where('name', 'Tenant-Specific Plan')->first();
    expect($plan)->not->toBeNull();
    
    // Switch to the second tenant
    tenancy()->end();
    tenancy()->initialize($this->anotherTenant);
    
    // Create an admin user in the second tenant
    $adminUser2 = User::factory()->create([
        'email' => 'admin2@example.com',
    ]);
    
    $adminRole2 = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $managePlansPermission2 = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'manage plans']);
    
    $adminRole2->givePermissionTo([
        $managePlansPermission2,
    ]);
    
    $adminUser2->assignRole($adminRole2);
    
    // Try to access the plan from the first tenant
    $this->actingAs($adminUser2);
    $response = $this->get(route('plans.show', $plan->id));
    $response->assertStatus(404); // Not found in this tenant
    
    // Verify that the plan doesn't exist in the second tenant
    expect(Plan::where('name', 'Tenant-Specific Plan')->exists())->toBeFalse();
    
    // Create a plan with the same name in the second tenant
    $this->post(route('plans.store'), $planData);
    $plan2 = Plan::where('name', 'Tenant-Specific Plan')->first();
    expect($plan2)->not->toBeNull();
    
    // Plans should have the same name but different IDs (tenant isolation)
    expect($plan2->id)->not->toBe($plan->id);
});

// Plan Feature CRUD Tests
it('[plan_feature_crud_happy_path] allows managing plan features', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Create a new plan
    $planData = [
        'name' => 'Feature Test Plan',
        'description' => 'A plan for testing features',
        'price' => 19.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ];
    
    $this->post(route('plans.store'), $planData);
    $plan = Plan::where('name', 'Feature Test Plan')->first();
    
    // Add features to the plan
    $featureData = [
        'name' => 'test_feature',
        'value' => 10,
        'description' => 'A test feature',
    ];
    
    $response = $this->post(route('plans.features.store', $plan->id), $featureData);
    $response->assertStatus(302); // Redirect after creation
    
    // Check that the feature was created
    $this->assertDatabaseHas('plan_features', [
        'plan_id' => $plan->id,
        'name' => 'test_feature',
        'value' => 10,
    ]);
    
    // Get the created feature
    $feature = PlanFeature::where('plan_id', $plan->id)
        ->where('name', 'test_feature')
        ->first();
    
    // Update the feature
    $updatedFeatureData = [
        'name' => 'test_feature',
        'value' => 20,
        'description' => 'An updated test feature',
    ];
    
    $response = $this->put(route('plans.features.update', [$plan->id, $feature->id]), $updatedFeatureData);
    $response->assertStatus(302); // Redirect after update
    
    // Check that the feature was updated
    $this->assertDatabaseHas('plan_features', [
        'id' => $feature->id,
        'plan_id' => $plan->id,
        'name' => 'test_feature',
        'value' => 20,
    ]);
    
    // Delete the feature
    $response = $this->delete(route('plans.features.destroy', [$plan->id, $feature->id]));
    $response->assertStatus(302); // Redirect after deletion
    
    // Check that the feature was deleted
    $this->assertDatabaseMissing('plan_features', [
        'id' => $feature->id,
        'deleted_at' => null,
    ]);
});
