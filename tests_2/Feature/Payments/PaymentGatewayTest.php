<?php

use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Plan;

beforeEach(function () {
    // Create payment gateway test environment
});

// Payment Gateway Tests
it('[payment_flow_happy_path] processes payments successfully', function () {
    // Create a tenant
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Initialize tenant
    tenancy()->initialize($tenant);
    
    // Create a user
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'name' => 'Test Customer',
    ]);
    
    // Create a plan
    $plan = Plan::create([
        'name' => 'Premium Plan',
        'description' => 'A premium plan for testing payments',
        'price' => 19.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ]);
    
    // Get the payment gateway
    $paymentGateway = app(\Nilit\LaraBoilerCore\Services\PaymentGatewayInterface::class);
    
    // Set up payment data
    $paymentData = [
        'amount' => $plan->price,
        'currency' => 'USD',
        'description' => "Payment for {$plan->name}",
        'source' => [
            'number' => '4242424242424242', // Test card number
            'exp_month' => 12,
            'exp_year' => date('Y') + 1,
            'cvc' => '123',
            'name' => $user->name,
        ],
        'metadata' => [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ],
    ];
    
    // Process the payment
    $result = $paymentGateway->processPayment($paymentData);
    
    // Check the result
    expect($result)->toBeArray();
    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeTrue();
    expect($result)->toHaveKey('transaction_id');
    
    // Check that the payment was logged
    $this->assertDatabaseHas('payment_transactions', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'amount' => $plan->price,
        'status' => 'completed',
    ]);
    
    // End tenancy
    tenancy()->end();
});

it('[payment_flow_tenant_context] processes payments in the correct tenant context', function () {
    // Create two tenants
    $tenant1 = Tenant::create([
        'id' => 'tenant-1-' . uniqid(),
        'name' => 'Tenant 1',
    ]);
    
    $tenant2 = Tenant::create([
        'id' => 'tenant-2-' . uniqid(),
        'name' => 'Tenant 2',
    ]);
    
    // Initialize the first tenant
    tenancy()->initialize($tenant1);
    
    // Create a user in the first tenant
    $user1 = User::factory()->create([
        'email' => 'user1@example.com',
        'name' => 'User in Tenant 1',
    ]);
    
    // Create a plan
    $plan = Plan::create([
        'name' => 'Basic Plan',
        'description' => 'A basic plan',
        'price' => 9.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ]);
    
    // Get the payment gateway
    $paymentGateway = app(\Nilit\LaraBoilerCore\Services\PaymentGatewayInterface::class);
    
    // Process a payment for the first tenant
    $paymentData1 = [
        'amount' => $plan->price,
        'currency' => 'USD',
        'description' => "Payment from Tenant 1",
        'source' => [
            'number' => '4242424242424242', // Test card number
            'exp_month' => 12,
            'exp_year' => date('Y') + 1,
            'cvc' => '123',
            'name' => $user1->name,
        ],
        'metadata' => [
            'tenant_id' => $tenant1->id,
            'user_id' => $user1->id,
            'plan_id' => $plan->id,
        ],
    ];
    
    $result1 = $paymentGateway->processPayment($paymentData1);
    
    // Check that the payment is recorded in tenant 1's context
    $this->assertDatabaseHas('payment_transactions', [
        'tenant_id' => $tenant1->id,
        'description' => "Payment from Tenant 1",
    ]);
    
    // Switch to the second tenant
    tenancy()->end();
    tenancy()->initialize($tenant2);
    
    // Create a user in the second tenant
    $user2 = User::factory()->create([
        'email' => 'user2@example.com',
        'name' => 'User in Tenant 2',
    ]);
    
    // Create the same plan in tenant 2
    $plan2 = Plan::create([
        'name' => 'Basic Plan',
        'description' => 'A basic plan',
        'price' => 9.99,
        'billing_period' => 'monthly',
        'is_active' => true,
    ]);
    
    // Process a payment for the second tenant
    $paymentData2 = [
        'amount' => $plan2->price,
        'currency' => 'USD',
        'description' => "Payment from Tenant 2",
        'source' => [
            'number' => '4242424242424242', // Test card number
            'exp_month' => 12,
            'exp_year' => date('Y') + 1,
            'cvc' => '123',
            'name' => $user2->name,
        ],
        'metadata' => [
            'tenant_id' => $tenant2->id,
            'user_id' => $user2->id,
            'plan_id' => $plan2->id,
        ],
    ];
    
    $result2 = $paymentGateway->processPayment($paymentData2);
    
    // Check that the payment is recorded in tenant 2's context
    $this->assertDatabaseHas('payment_transactions', [
        'tenant_id' => $tenant2->id,
        'description' => "Payment from Tenant 2",
    ]);
    
    // The payment from tenant 1 should not be visible in tenant 2's context
    $this->assertDatabaseMissing('payment_transactions', [
        'description' => "Payment from Tenant 1",
    ]);
    
    // End tenancy
    tenancy()->end();
});
