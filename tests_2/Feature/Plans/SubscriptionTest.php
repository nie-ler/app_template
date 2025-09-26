<?php

use Nilit\LaraBoilerCore\Models\Subscription;
use Nilit\LaraBoilerCore\Models\SubscriptionItem;
use Nilit\LaraBoilerCore\Models\Plan;
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
    $manageSubscriptionsPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'manage subscriptions']);
    
    $adminRole->givePermissionTo([
        $manageSubscriptionsPermission,
    ]);
    
    $adminUser->assignRole($adminRole);
    
    // Create regular user with no special permissions
    $regularUser = User::factory()->create([
        'email' => 'user@example.com',
    ]);
    
    // Create a plan for subscriptions
    $plan = Plan::create([
        'name' => 'Basic Plan',
        'description' => 'A basic plan for testing subscriptions',
        'price' => 9.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ]);
    
    // Make these entities available to the tests
    $this->adminUser = $adminUser;
    $this->regularUser = $regularUser;
    $this->tenant = $tenant;
    $this->plan = $plan;
    
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

// Subscription CRUD Tests
it('[subscription_crud_happy_path] allows creating, reading, updating, and deleting subscriptions', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Create a new subscription
    $subscriptionData = [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
        'ends_at' => now()->addMonth(),
    ];
    
    $response = $this->post(route('subscriptions.store'), $subscriptionData);
    $response->assertStatus(302); // Redirect after creation
    
    // Check that the subscription was created
    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);
    
    // Get the created subscription
    $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
    
    // Read the subscription
    $response = $this->get(route('subscriptions.show', $subscription->id));
    $response->assertStatus(200);
    $response->assertSee($this->plan->name);
    
    // Update the subscription
    $updatedData = [
        'status' => 'paused',
        'ends_at' => now()->addMonths(2),
    ];
    
    $response = $this->put(route('subscriptions.update', $subscription->id), $updatedData);
    $response->assertStatus(302); // Redirect after update
    
    // Check that the subscription was updated
    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'status' => 'paused',
    ]);
    
    // Cancel the subscription
    $response = $this->put(route('subscriptions.cancel', $subscription->id));
    $response->assertStatus(302); // Redirect after cancellation
    
    // Check that the subscription was cancelled
    $subscription->refresh();
    expect($subscription->status)->toBe('cancelled');
    expect($subscription->ends_at)->not->toBeNull();
    
    // Delete the subscription
    $response = $this->delete(route('subscriptions.destroy', $subscription->id));
    $response->assertStatus(302); // Redirect after deletion
    
    // Check that the subscription was deleted
    $this->assertSoftDeleted('subscriptions', [
        'id' => $subscription->id,
    ]);
});

it('[subscription_crud_validation] validates subscription data', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Create a subscription with invalid data
    $invalidSubscriptionData = [
        'tenant_id' => $this->tenant->id,
        'plan_id' => 9999, // Non-existent plan
        'status' => 'invalid-status', // Invalid status
    ];
    
    $response = $this->post(route('subscriptions.store'), $invalidSubscriptionData);
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['plan_id', 'status']);
    
    // Create a valid subscription to test update validation
    $validSubscriptionData = [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'ends_at' => now()->addMonth(),
    ];
    
    $this->post(route('subscriptions.store'), $validSubscriptionData);
    $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
    
    // Update with invalid data
    $invalidUpdateData = [
        'status' => 'invalid-status', // Invalid status
    ];
    
    $response = $this->put(route('subscriptions.update', $subscription->id), $invalidUpdateData);
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['status']);
});

it('[subscription_crud_permission_denied] prevents unauthorized users from managing subscriptions', function () {
    // Test as regular user without permissions
    $this->actingAs($this->regularUser);
    
    // Try to create a subscription
    $subscriptionData = [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'ends_at' => now()->addMonth(),
    ];
    
    $response = $this->post(route('subscriptions.store'), $subscriptionData);
    $response->assertStatus(403); // Forbidden
    
    // Create a subscription as admin
    $this->actingAs($this->adminUser);
    $this->post(route('subscriptions.store'), $subscriptionData);
    $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
    
    // Try to update the subscription as regular user
    $this->actingAs($this->regularUser);
    $updatedData = [
        'status' => 'paused',
    ];
    
    $response = $this->put(route('subscriptions.update', $subscription->id), $updatedData);
    $response->assertStatus(403); // Forbidden
    
    // Try to cancel the subscription as regular user
    $response = $this->put(route('subscriptions.cancel', $subscription->id));
    $response->assertStatus(403); // Forbidden
    
    // Try to delete the subscription as regular user
    $response = $this->delete(route('subscriptions.destroy', $subscription->id));
    $response->assertStatus(403); // Forbidden
});

it('[subscription_crud_edge_cross_tenant] ensures subscriptions are tenant-specific', function () {
    // Initialize the first tenant
    tenancy()->initialize($this->tenant);
    
    // Create a subscription for the first tenant as admin
    $this->actingAs($this->adminUser);
    $subscriptionData = [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'ends_at' => now()->addMonth(),
    ];
    
    $this->post(route('subscriptions.store'), $subscriptionData);
    $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
    expect($subscription)->not->toBeNull();
    
    // Switch to the second tenant
    tenancy()->end();
    tenancy()->initialize($this->anotherTenant);
    
    // Create an admin user in the second tenant
    $adminUser2 = User::factory()->create([
        'email' => 'admin2@example.com',
    ]);
    
    $adminRole2 = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $manageSubscriptionsPermission2 = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'manage subscriptions']);
    
    $adminRole2->givePermissionTo([
        $manageSubscriptionsPermission2,
    ]);
    
    $adminUser2->assignRole($adminRole2);
    
    // Create a plan in the second tenant
    $plan2 = Plan::create([
        'name' => 'Second Tenant Plan',
        'description' => 'A plan for the second tenant',
        'price' => 19.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ]);
    
    // Try to access the subscription from the first tenant
    $this->actingAs($adminUser2);
    $response = $this->get(route('subscriptions.show', $subscription->id));
    $response->assertStatus(404); // Not found in this tenant
    
    // Create a subscription for the second tenant
    $subscription2Data = [
        'tenant_id' => $this->anotherTenant->id,
        'plan_id' => $plan2->id,
        'status' => 'active',
        'ends_at' => now()->addMonth(),
    ];
    
    $this->post(route('subscriptions.store'), $subscription2Data);
    $subscription2 = Subscription::where('tenant_id', $this->anotherTenant->id)->first();
    expect($subscription2)->not->toBeNull();
    
    // The subscriptions should be different
    expect($subscription2->id)->not->toBe($subscription->id);
});

// Subscription Items Tests
it('[subscription_item_crud_happy_path] allows managing subscription items', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Create a subscription
    $subscriptionData = [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'ends_at' => now()->addMonth(),
    ];
    
    $this->post(route('subscriptions.store'), $subscriptionData);
    $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
    
    // Add an item to the subscription
    $itemData = [
        'subscription_id' => $subscription->id,
        'name' => 'Extra Storage',
        'description' => '10GB additional storage',
        'quantity' => 1,
        'unit_price' => 5.99,
    ];
    
    $response = $this->post(route('subscriptions.items.store', $subscription->id), $itemData);
    $response->assertStatus(302); // Redirect after creation
    
    // Check that the item was created
    $this->assertDatabaseHas('subscription_items', [
        'subscription_id' => $subscription->id,
        'name' => 'Extra Storage',
        'unit_price' => 5.99,
    ]);
    
    // Get the created item
    $item = SubscriptionItem::where('subscription_id', $subscription->id)
        ->where('name', 'Extra Storage')
        ->first();
    
    // Update the item
    $updatedItemData = [
        'quantity' => 2,
        'unit_price' => 4.99, // Discount for quantity
    ];
    
    $response = $this->put(route('subscriptions.items.update', [$subscription->id, $item->id]), $updatedItemData);
    $response->assertStatus(302); // Redirect after update
    
    // Check that the item was updated
    $this->assertDatabaseHas('subscription_items', [
        'id' => $item->id,
        'subscription_id' => $subscription->id,
        'quantity' => 2,
        'unit_price' => 4.99,
    ]);
    
    // Delete the item
    $response = $this->delete(route('subscriptions.items.destroy', [$subscription->id, $item->id]));
    $response->assertStatus(302); // Redirect after deletion
    
    // Check that the item was deleted
    $this->assertDatabaseMissing('subscription_items', [
        'id' => $item->id,
        'deleted_at' => null,
    ]);
});
