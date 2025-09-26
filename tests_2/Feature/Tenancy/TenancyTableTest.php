<?php

use Nilit\LaraBoilerCore\Models\User;
use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Tests\TestCase;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);


beforeEach(function () {
    // Create two tenants for testing
    $tenant1 = Tenant::create([
        'id' => 'test-tenant-1-' . uniqid(),
        'name' => 'Test Tenant 1',
        'email' => 'T1'.uniqid().'@example.com',        
    ]);
    
    // Store tenants for the tests
    $this->tenant1 = $tenant1;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

it('[multi_tenancy_db_separation_happy_path] stores tenant data in the correct database', function () {
    // Initialize first tenant
    tenancy()->initialize($this->tenant1);

    expect(Schema::connection('tenant')->hasTable('tenant_audit_logs'))->toBeTrue();
});
