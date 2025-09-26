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
    
    // Create a basic plan with features
    $basicPlan = Plan::create([
        'name' => 'Basic Plan',
        'description' => 'A basic plan with limited features',
        'price' => 9.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ]);
    
    // Add features to the basic plan
    $basicPlanFeatures = [
        ['name' => 'users', 'value' => 10, 'description' => 'Up to 10 users'],
        ['name' => 'storage', 'value' => 5, 'description' => '5GB storage'],
        ['name' => 'feature_a', 'value' => true, 'description' => 'Feature A enabled'],
        ['name' => 'feature_b', 'value' => false, 'description' => 'Feature B disabled'],
    ];
    
    foreach ($basicPlanFeatures as $feature) {
        PlanFeature::create([
            'plan_id' => $basicPlan->id,
            'name' => $feature['name'],
            'value' => $feature['value'],
            'description' => $feature['description'],
        ]);
    }
    
    // Create a premium plan with features
    $premiumPlan = Plan::create([
        'name' => 'Premium Plan',
        'description' => 'A premium plan with all features',
        'price' => 29.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ]);
    
    // Add features to the premium plan
    $premiumPlanFeatures = [
        ['name' => 'users', 'value' => 50, 'description' => 'Up to 50 users'],
        ['name' => 'storage', 'value' => 50, 'description' => '50GB storage'],
        ['name' => 'feature_a', 'value' => true, 'description' => 'Feature A enabled'],
        ['name' => 'feature_b', 'value' => true, 'description' => 'Feature B enabled'],
        ['name' => 'feature_c', 'value' => true, 'description' => 'Feature C enabled'],
    ];
    
    foreach ($premiumPlanFeatures as $feature) {
        PlanFeature::create([
            'plan_id' => $premiumPlan->id,
            'name' => $feature['name'],
            'value' => $feature['value'],
            'description' => $feature['description'],
        ]);
    }
    
    // Assign the basic plan to the tenant
    $tenant->plan_id = $basicPlan->id;
    $tenant->save();
    
    // Store test data
    $this->tenant = $tenant;
    $this->adminUser = $adminUser;
    $this->regularUser = $regularUser;
    $this->basicPlan = $basicPlan;
    $this->premiumPlan = $premiumPlan;
    
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

// Plan Feature Tests
it('[plan_feature_check_happy_path] grants access to features in the plan', function () {
    // The tenant is on the basic plan, which includes feature_a but not feature_b
    
    // Check that the tenant has access to feature_a
    expect($this->tenant->hasFeature('feature_a'))->toBeTrue();
    
    // Check that the tenant has the correct value for "users" feature
    expect($this->tenant->getFeatureValue('users'))->toBe(10);
    
    // Check that feature_a is enabled through the helper function
    expect($this->tenant->hasFeature('feature_a', true))->toBeTrue();
});

it('[plan_feature_check_denied] denies access to features not in the plan', function () {
    // Check that the tenant doesn't have access to feature_c (premium only)
    expect($this->tenant->hasFeature('feature_c'))->toBeFalse();
    
    // Check that feature_b is disabled
    expect($this->tenant->hasFeature('feature_b', true))->toBeFalse();
    
    // Check that a non-existent feature returns false
    expect($this->tenant->hasFeature('non_existent_feature'))->toBeFalse();
});

it('[plan_feature_validation] validates feature checks', function () {
    // Test with null feature name
    expect($this->tenant->hasFeature(null))->toBeFalse();
    
    // Test with empty string
    expect($this->tenant->hasFeature(''))->toBeFalse();
    
    // Test with invalid feature name type
    expect($this->tenant->hasFeature(['invalid_type']))->toBeFalse();
    
    // Test getFeatureValue with non-existent feature
    expect($this->tenant->getFeatureValue('non_existent_feature'))->toBeNull();
    
    // Test getFeatureValue with null
    expect($this->tenant->getFeatureValue(null))->toBeNull();
});

it('[plan_feature_edge_no_subscription] handles case when tenant has no plan', function () {
    // Remove the plan from the tenant
    $this->tenant->plan_id = null;
    $this->tenant->save();
    
    // Refresh the tenant from the database
    $this->tenant->refresh();
    
    // Check that the tenant doesn't have any features
    expect($this->tenant->hasFeature('feature_a'))->toBeFalse();
    expect($this->tenant->hasFeature('users'))->toBeFalse();
    expect($this->tenant->getFeatureValue('storage'))->toBeNull();
});

it('[plan_feature_edge_expired_subscription] handles expired subscriptions', function () {
    // Create a subscription for the tenant
    $subscription = \Nilit\LaraBoilerCore\Models\Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->basicPlan->id,
        'status' => 'active',
        'trial_ends_at' => null,
        'ends_at' => now()->addDays(30), // 30 days from now
        'created_at' => now(),
    ]);
    
    // Check that the feature is available with an active subscription
    expect($this->tenant->hasFeature('feature_a'))->toBeTrue();
    
    // Set the subscription to expired
    $subscription->ends_at = now()->subDay(); // Yesterday
    $subscription->save();
    
    // Refresh the tenant
    $this->tenant->refresh();
    
    // With an expired subscription, features should not be available
    // Note: This depends on how your implementation handles expired subscriptions
    // This is a sample test assuming features are not available with expired subscriptions
    expect($this->tenant->hasActiveSubscription())->toBeFalse();
    expect($this->tenant->hasFeature('feature_a'))->toBeFalse();
});
