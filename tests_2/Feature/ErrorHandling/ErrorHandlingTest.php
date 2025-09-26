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
    
    // Create a test user
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
    
    // Make entities available to tests
    $this->user = $user;
    $this->tenant = $tenant;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Error Handling Tests
it('[error_invalid_requests] handles invalid or malformed requests', function () {
    // Test an API endpoint with an invalid JSON request
    $response = $this->postJson('/api/users', ['invalid' => 'json', 'without' => 'required_fields']);
    $response->assertStatus(422); // Unprocessable Entity
    $response->assertJsonValidationErrors(['name', 'email']);
    
    // Test a form submission with invalid CSRF token
    $response = $this->withHeaders([
        'X-CSRF-TOKEN' => 'invalid-token',
    ])->post('/users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
    
    $response->assertStatus(419); // CSRF token mismatch
    
    // Test a request with invalid content type
    $response = $this->withHeaders([
        'Content-Type' => 'text/plain',
    ])->postJson('/api/users', 'This is not JSON');
    
    $response->assertStatus(400); // Bad Request
});

it('[error_missing_data] handles system behavior when required data is missing', function () {
    // Test access to a non-existent user
    $nonExistentId = User::max('id') + 1000;
    
    $response = $this->get("/users/{$nonExistentId}");
    $response->assertStatus(404); // Not Found
    
    // Test access to a non-existent tenant
    tenancy()->end();
    
    try {
        tenancy()->initialize('non-existent-tenant');
        $this->fail('Should have thrown an exception');
    } catch (\Exception $e) {
        // This is expected
        expect($e)->toBeInstanceOf(\Exception::class);
    }
    
    // Test submitting a form with missing required fields
    tenancy()->initialize($this->tenant);
    
    $this->post('/users', [
        // Missing name and email
    ])->assertStatus(302); // Redirects back with errors
    
    $this->assertSessionHasErrors(['name', 'email']);
});

it('[error_permission_denied] handles unauthorized actions', function () {
    // Test access to an admin route as a non-admin user
    $response = $this->actingAs($this->user)
        ->get('/admin/settings');
    
    $response->assertStatus(403); // Forbidden
    
    // Test API access without proper token
    $response = $this->getJson('/api/admin/users');
    $response->assertStatus(401); // Unauthorized
    
    // Test access to another tenant's data
    $anotherTenant = Tenant::create([
        'id' => 'another-tenant-' . uniqid(),
        'name' => 'Another Tenant',
    ]);
    
    // Try to access another tenant's route with current tenant session
    $response = $this->actingAs($this->user)
        ->get("/tenant/{$anotherTenant->id}/dashboard");
    
    $response->assertStatus(403); // Forbidden
});

it('[error_self_deletion_prevention] prevents users from deleting themselves', function () {
    // Try to delete the currently authenticated user
    $response = $this->actingAs($this->user)
        ->delete("/users/{$this->user->id}");
    
    $response->assertStatus(422); // Unprocessable Entity
    $response->assertSessionHasErrors(['user' => 'You cannot delete your own account.']);
    
    // Check that the user was not deleted
    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'deleted_at' => null,
    ]);
});
