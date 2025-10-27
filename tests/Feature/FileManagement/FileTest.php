<?php

use Nilit\LaraBoilerCore\Models\File;
use Nilit\LaraBoilerCore\Models\Role;
use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Create tenant for testing
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
        'email' => 'tenant_test_77@example.com',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Use default storage (tenant-aware automatically)
    Storage::fake();
    
    // Create admin user with all permissions
    $adminUser = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    
    $adminUser->assignRole('owner');
    $adminUser->tenant_id = $tenant->id;
    $adminUser->save();    
    
    // Create regular user with no special permissions
    $regularUser = User::factory()->create([
        'email' => 'user@example.com',
    ]);
    
    // Make these entities available to the tests
    $this->adminUser = $adminUser;
    $this->regularUser = $regularUser;
    $this->tenant = $tenant;
    
    // Create another tenant for cross-tenant tests
    $anotherTenant = Tenant::create([
        'id' => 'another-tenant-' . uniqid(),
        'name' => 'Another Tenant',
        'email' => 'tenant_test_9@example.com',
    ]);
    $this->anotherTenant = $anotherTenant;
});

afterEach(function () {
    // Clean up: end tenancy
    $this->adminUser->forceDelete();
    $this->regularUser->forceDelete();
    $this->tenant->delete();
    $this->anotherTenant->delete();
    tenancy()->end();
});

// File CRUD Tests
it('allows uploading, viewing, and deleting files', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Upload a file
    $file = UploadedFile::fake()->create('document.pdf', 500);
    
    $response = $this->post(route('tenant.files.store', ['tenant' => $this->tenant]), [
        'file' => $file,
        'description' => 'A test PDF document',
    ]);
    
    $response->assertStatus(302); // Redirect after upload
    
    // Check that the file was stored in the database (File model uses 'central' connection)
    $this->assertDatabaseHas('files', [
        'tenant_id' => $this->tenant->id,
        'original_name' => 'document.pdf',
        'description' => 'A test PDF document',
        'mime_type' => 'application/pdf',
    ], 'central');
    
    // Get the uploaded file
    $uploadedFile = File::where('tenant_id', $this->tenant->id)
        ->where('original_name', 'document.pdf')
        ->first();
    
    // Verify the file exists in storage
    Storage::assertExists("{$uploadedFile->path}/{$uploadedFile->hashed_name}");
    
    // View the file details
    $response = $this->get(route('tenant.files.show', ['tenant' => $this->tenant, 'file' => $uploadedFile->id]));
    $response->assertStatus(200);
    $response->assertSee('document.pdf');
    
    // Download the file
    $response = $this->get(route('tenant.files.download', ['tenant' => $this->tenant, 'file' => $uploadedFile->id]));
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    
    // Delete the file
    $fileIdToDelete = $uploadedFile->id;
    $filePath = "{$uploadedFile->path}/{$uploadedFile->hashed_name}";
    
    $response = $this->delete(route('tenant.files.destroy', ['tenant' => $this->tenant, 'file' => $uploadedFile->id]));
    $response->assertStatus(302); // Redirect after deletion

    // Refresh the model to check if it still exists
    $deletedFile = File::find($fileIdToDelete);
    expect($deletedFile)->toBeNull();
    
    // Check that the file was deleted from storage
    Storage::assertMissing($filePath);    

    // Check that the file was deleted from the database (hard delete, not soft delete)
    $this->assertDatabaseMissing('files', [
        'id' => $uploadedFile->id,
    ], 'central');
});

it('validates file uploads', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // The controller only validates 'file' and 'description' (description is optional)
    // So we test file validation only
    
    // Upload without a file
    $response = $this->post(route('tenant.files.store', ['tenant' => $this->tenant]), [
        'description' => 'A test PDF document',
    ]);
    
    $response->assertStatus(302); // Redirect back with errors
    $response->assertSessionHasErrors(['file']);
    
    // Upload a file that's too large (controller limit is 10240 KB = 10MB)
    $largeFile = UploadedFile::fake()->create('large.pdf', 10241); // Just over 10MB
    
    $response = $this->post(route('tenant.files.store', ['tenant' => $this->tenant]), [
        'file' => $largeFile,
        'description' => 'A very large file',
    ]);
    
    $response->assertStatus(302); // Redirect back with errors
    $response->assertSessionHasErrors(['file']);
});

it('prevents unauthorized users from managing files', function () {
    // Test as regular user without permissions
    $this->actingAs($this->regularUser);
    
    // Try to upload a file - controller checks permission with redirect
    $file = UploadedFile::fake()->create('document.pdf', 500);
    
    $response = $this->post(route('tenant.files.store', ['tenant' => $this->tenant]), [
        'file' => $file,
        'description' => 'This should fail',
    ]);
    
    // Expect either redirect with error message or 403
    if ($response->status() === 302) {
        $response->assertSessionHas('error');
    } else {
        $response->assertStatus(403);
    }
    
    // Upload a file as admin
    $this->actingAs($this->adminUser);
    
    $this->post(route('tenant.files.store', ['tenant' => $this->tenant]), [
        'file' => $file,
        'description' => 'This should work',
    ]);
    
    $uploadedFile = File::where('tenant_id', $this->tenant->id)
        ->where('original_name', 'document.pdf')
        ->first();
    
    // Try to delete the file as regular user
    $this->actingAs($this->regularUser);
    
    $response = $this->delete(route('tenant.files.destroy', ['tenant' => $this->tenant, 'file' => $uploadedFile->id]));
    
    // Expect either redirect with error message or 403
    if ($response->status() === 302) {
        $response->assertSessionHas('error');
    } else {
        $response->assertStatus(403);
    }
});

it('enforces file access control', function () {    
    // Create a viewer user
    $viewerUser = User::factory()->create([
        'email' => 'viewer_' . uniqid() . '@example.com',
        'tenant_id' => $this->tenant->id,
    ]);
    
    $viewerUser->assignRole('user');
    
    // Upload a file as admin
    $this->actingAs($this->adminUser);
    
    $file = UploadedFile::fake()->create('policy-test.pdf', 500);
    $this->post(route('tenant.files.store', ['tenant' => $this->tenant]), [
        'file' => $file,
        'description' => 'Testing file policies',
    ]);
    
    $uploadedFile = File::where('tenant_id', $this->tenant->id)
        ->where('original_name', 'policy-test.pdf')
        ->first();
    
    // Test as viewer user
    $this->actingAs($viewerUser);
    
    // Should be able to view the file (has 'view tenant files' permission)
    $response = $this->get(route('tenant.files.show', ['tenant' => $this->tenant, 'file' => $uploadedFile->id]));
    $response->assertStatus(200);
    
    // Should be able to download the file
    $response = $this->get(route('tenant.files.download', ['tenant' => $this->tenant, 'file' => $uploadedFile->id]));
    $response->assertStatus(200);
    
    // Should NOT be able to delete the file (no 'delete files' permission)
    $response = $this->delete(route('tenant.files.destroy', ['tenant' => $this->tenant, 'file' => $uploadedFile->id]));
    
    // Expect either redirect with error message or 403
    if ($response->status() === 302) {
        $response->assertSessionHas('error');
    } else {
        $response->assertStatus(403);
    }
});

it('prevents cross-tenant file access', function () {
    // Upload a file in the first tenant as admin
    $this->actingAs($this->adminUser);
    
    $file = UploadedFile::fake()->create('cross-tenant-test.pdf', 500);
    $this->post(route('tenant.files.store', ['tenant' => $this->tenant]), [
        'file' => $file,
        'description' => 'A file in the first tenant',
    ]);
    
    $uploadedFile = File::where('tenant_id', $this->tenant->id)
        ->where('original_name', 'cross-tenant-test.pdf')
        ->first();
    $fileId = $uploadedFile->id;
    
    // Switch to the second tenant
    tenancy()->end();
    tenancy()->initialize($this->anotherTenant);
    
    // Create an admin user in the second tenant with unique email
    $adminUser2 = User::factory()->create([
        'email' => 'admin2_' . uniqid() . '@example.com',
    ]);
    
    $adminUser2->assignRole('owner');
    
    // Try to access the file from the first tenant (should fail due to FilePolicy checking tenant_id)
    $this->actingAs($adminUser2);
    
    $response = $this->get(route('tenant.files.show', ['tenant' => $this->anotherTenant, 'file' => $fileId]));
    $response->assertStatus(403); // Forbidden by policy
    
    $response = $this->get(route('tenant.files.download', ['tenant' => $this->anotherTenant, 'file' => $fileId]));
    $response->assertStatus(403); // Forbidden by policy
    
    $response = $this->delete(route('tenant.files.destroy', ['tenant' => $this->anotherTenant, 'file' => $fileId]));
    $response->assertStatus(403); // Forbidden by policy
});
