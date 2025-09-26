<?php

use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Services\AuditService;

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
    $this->auditService = app(AuditService::class);
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// Audit Service Tests
it('[audit_service_happy_path] logs activities to the appropriate database', function () {
    // Log an activity in tenant context
    $this->auditService->logActivity('user.login', [
        'id' => $this->user->id,
        'email' => $this->user->email,
    ]);
    
    // Check tenant audit log
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.login',
        'subject_id' => $this->user->id,
        'subject_type' => get_class($this->user),
    ]);
    
    // End tenancy and log a central activity
    tenancy()->end();
    
    $this->auditService->logActivity('tenant.login', [
        'id' => $this->tenant->id,
        'name' => $this->tenant->name,
    ]);
    
    // Check central audit log
    $this->assertDatabaseHas('central_audit_logs', [
        'description' => 'tenant.login',
        'subject_id' => $this->tenant->id,
        'subject_type' => get_class($this->tenant),
    ]);
});

it('[audit_service_validation] validates and sanitizes log data', function () {
    // Test with valid data
    $this->auditService->logActivity('user.action', [
        'id' => $this->user->id,
        'email' => $this->user->email,
        'details' => 'Valid data',
    ]);
    
    // Test with potential XSS content
    $this->auditService->logActivity('user.xss_test', [
        'id' => $this->user->id,
        'html' => '<script>alert("XSS");</script>',
    ]);
    
    // Get the log
    $log = \Nilit\LaraBoilerCore\Models\TenantAuditLog::where('description', 'user.xss_test')->first();
    
    // Check that the HTML was sanitized or encoded
    expect($log->properties->toArray())->toHaveKey('html');
    expect($log->properties['html'])->not->toContain('<script>');
    
    // Test with invalid UTF-8 data
    $invalidUtf8 = "Test \xC0\xAF invalid UTF-8";
    $this->auditService->logActivity('user.encoding_test', [
        'id' => $this->user->id,
        'text' => $invalidUtf8,
    ]);
    
    // Get the log
    $log = \Nilit\LaraBoilerCore\Models\TenantAuditLog::where('description', 'user.encoding_test')->first();
    
    // Check that the text was properly sanitized
    expect($log->properties->toArray())->toHaveKey('text');
});

it('[audit_service_edge_transaction_rollback] handles transaction rollbacks correctly', function () {
    // Start a database transaction
    DB::beginTransaction();
    
    // Log an activity within the transaction
    $this->auditService->logActivity('transaction.test', [
        'id' => $this->user->id,
        'message' => 'This should be rolled back',
    ]);
    
    // Check that the log entry exists
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'transaction.test',
    ]);
    
    // Roll back the transaction
    DB::rollBack();
    
    // Check that the log entry no longer exists
    $this->assertDatabaseMissing('tenant_audit_logs', [
        'description' => 'transaction.test',
    ]);
});

// LogsActivity Trait Tests
it('[logs_activity_trait_happy_path] delegates to AuditService correctly', function () {
    // Create a model that uses the LogsActivity trait
    $testModel = new \Nilit\LaraBoilerCore\Models\TestModel();
    $testModel->name = 'Test Activity Model';
    $testModel->save();
    
    // Check that the model creation was logged
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'test.created',
        'subject_id' => $testModel->id,
        'subject_type' => get_class($testModel),
    ]);
    
    // Update the model
    $testModel->name = 'Updated Name';
    $testModel->save();
    
    // Check that the update was logged
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'test.updated',
        'subject_id' => $testModel->id,
    ]);
    
    // Delete the model
    $testModel->delete();
    
    // Check that the deletion was logged
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'test.deleted',
        'subject_id' => $testModel->id,
    ]);
});

it('[logs_activity_trait_security_events] flags security events properly', function () {
    // Simulate a user update with security implications
    // First, authenticate as the user
    $this->actingAs($this->user);
    
    // Update security-sensitive field
    $this->user->email = 'newemail@example.com';
    $this->user->save();
    
    // Check that the update was logged as a security event
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.updated',
        'subject_id' => $this->user->id,
        'is_security_event' => true,
    ]);
    
    // Check that it was also logged in the central audit log
    tenancy()->end();
    
    $this->assertDatabaseHas('central_audit_logs', [
        'description' => 'user.updated',
        'tenant_id' => $this->tenant->id,
        'is_security_event' => true,
    ]);
});
