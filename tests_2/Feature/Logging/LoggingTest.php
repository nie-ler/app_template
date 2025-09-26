<?php

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\CentralAuditLog;
use Nilit\LaraBoilerCore\Models\TenantAuditLog;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create a tenant for testing
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
        'email' => 'test@example.com',
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

// Logging Tests
it('[logging_activity_happy_path] logs all user actions correctly', function () {
    // Initialize the AuditService
    $auditService = app(\Nilit\LaraBoilerCore\Services\AuditService::class);
    
    // Log a user creation event
    $auditService->logActivity('user.created', [
        'id' => $this->user->id,
        'name' => $this->user->name,
        'email' => $this->user->email
    ]);
    
    // Check the tenant audit log
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.created',
        'subject_id' => $this->user->id,
        'subject_type' => get_class($this->user),
        'causer_id' => null,
        'causer_type' => null,
    ]);
    
    // Log activity with authenticated user
    $this->actingAs($this->user);
    
    $auditService->logActivity('user.updated', [
        'id' => $this->user->id,
        'name' => 'Updated Name',
    ]);
    
    // Check log contains the authenticated user as causer
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.updated',
        'subject_id' => $this->user->id,
        'causer_id' => $this->user->id,
        'causer_type' => get_class($this->user),
    ]);
    
    // Log a user deletion
    $auditService->logActivity('user.deleted', [
        'id' => $this->user->id,
    ]);
    
    // Check the log
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.deleted',
        'subject_id' => $this->user->id,
    ]);
});

it('[logging_activity_validation] logs contain correct action type, user, and timestamp', function () {
    $auditService = app(\Nilit\LaraBoilerCore\Services\AuditService::class);
    
    // Get the current timestamp for comparison
    $before = now();
    
    // Act as the user
    $this->actingAs($this->user);
    
    // Log an action
    $auditService->logActivity('user.logged_in', [
        'id' => $this->user->id,
        'ip_address' => '127.0.0.1'
    ]);
    
    $after = now();
    
    // Get the log from the database
    $log = TenantAuditLog::latest()->first();
    
    // Validate the log details
    expect($log)->not->toBeNull();
    expect($log->description)->toBe('user.logged_in');
    expect($log->subject_id)->toBe($this->user->id);
    expect($log->subject_type)->toBe(get_class($this->user));
    expect($log->causer_id)->toBe($this->user->id);
    expect($log->causer_type)->toBe(get_class($this->user));
    expect($log->properties->toArray())->toHaveKey('id');
    expect($log->properties->toArray())->toHaveKey('ip_address');
    
    // Check the timestamp is recent
    expect($log->created_at->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
    expect($log->created_at->timestamp)->toBeLessThanOrEqual($after->timestamp);
});

it('[logging_activity_permission_denied] does not log unauthorized actions', function () {
    $auditService = app(\Nilit\LaraBoilerCore\Services\AuditService::class);
    
    // Create a controller-like method that logs only if user has permission
    $performAndLogAction = function ($user, $action, $data) use ($auditService) {
        // Check if user has permission (simulated)
        $hasPermission = $action === 'user.view'; // Only allow viewing
        
        if (!$hasPermission) {
            return false;
        }
        
        // Log the activity since it was authorized
        $auditService->logActivity($action, $data);
        return true;
    };
    
    // Act as the user
    $this->actingAs($this->user);
    
    // Permitted action
    $performAndLogAction($this->user, 'user.view', ['id' => $this->user->id]);
    
    // Non-permitted action
    $performAndLogAction($this->user, 'user.delete', ['id' => $this->user->id]);
    
    // Only the authorized action should be logged
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.view',
    ]);
    
    $this->assertDatabaseMissing('tenant_audit_logs', [
        'description' => 'user.delete',
    ]);
});

it('[logging_activity_edge_missing_data] handles missing or malformed data gracefully', function () {
    $auditService = app(\Nilit\LaraBoilerCore\Services\AuditService::class);
    
    // Log with missing subject ID
    $auditService->logActivity('user.viewed', [
        // No ID provided
        'name' => 'Some User',
    ]);
    
    // Log should still be created
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.viewed',
    ]);
    
    // Log with null data
    $auditService->logActivity('system.error', null);
    
    // Log should still be created with empty properties
    $log = TenantAuditLog::where('description', 'system.error')->first();
    expect($log)->not->toBeNull();
    expect($log->properties->toArray())->toBe([]);
    
    // Log with malformed data (object that can't be JSON serialized)
    $resource = tmpfile(); // Resources can't be JSON encoded
    $auditService->logActivity('system.warning', ['resource' => $resource]);
    
    // Log should be created, but the unserializable data should be handled
    $log = TenantAuditLog::where('description', 'system.warning')->first();
    expect($log)->not->toBeNull();
    
    // Clean up
    fclose($resource);
});

it('[logging_security_event_happy_path] logs security events correctly', function () {
    $auditService = app(\Nilit\LaraBoilerCore\Services\AuditService::class);
    
    // Act as the user
    $this->actingAs($this->user);
    
    // Log a security event
    $auditService->logSecurityEvent('password.reset', [
        'id' => $this->user->id,
        'ip_address' => '127.0.0.1'
    ]);
    
    // Check both tenant and central logs
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'password.reset',
        'subject_id' => $this->user->id,
        'is_security_event' => true,
    ]);
    
    // End tenancy to check central logs
    tenancy()->end();
    
    $this->assertDatabaseHas('central_audit_logs', [
        'description' => 'password.reset',
        'tenant_id' => $this->tenant->id,
        'is_security_event' => true,
    ]);
});

it('[logging_security_event_validation] ensures security event logs have required fields', function () {
    $auditService = app(\Nilit\LaraBoilerCore\Services\AuditService::class);
    
    // Log a security event with all required fields
    $auditService->logSecurityEvent('email.changed', [
        'id' => $this->user->id,
        'old_email' => 'old@example.com',
        'new_email' => 'new@example.com',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0'
    ]);
    
    // Get the logs
    $tenantLog = TenantAuditLog::where('description', 'email.changed')->first();
    
    // Check required security fields
    expect($tenantLog)->not->toBeNull();
    expect($tenantLog->is_security_event)->toBeTrue();
    expect($tenantLog->properties->toArray())->toHaveKey('id');
    expect($tenantLog->properties->toArray())->toHaveKey('ip_address');
    expect($tenantLog->properties->toArray())->toHaveKey('user_agent');
});

it('[logging_completeness_tenant_context] stores logs in correct context', function () {
    $auditService = app(\Nilit\LaraBoilerCore\Services\AuditService::class);
    
    // Log in tenant context
    $auditService->logActivity('user.action', [
        'id' => $this->user->id,
    ]);
    
    // Check tenant log
    $this->assertDatabaseHas('tenant_audit_logs', [
        'description' => 'user.action',
    ]);
    
    // End tenancy and log in central context
    tenancy()->end();
    
    $auditService->logActivity('tenant.created', [
        'id' => $this->tenant->id,
    ]);
    
    // Check central log
    $this->assertDatabaseHas('central_audit_logs', [
        'description' => 'tenant.created',
    ]);
    
    // Log a security event in central context
    $auditService->logSecurityEvent('admin.login', [
        'email' => 'admin@example.com',
        'ip_address' => '127.0.0.1',
    ]);
    
    // Check it appears only in central log
    $this->assertDatabaseHas('central_audit_logs', [
        'description' => 'admin.login',
        'is_security_event' => true,
    ]);
});
