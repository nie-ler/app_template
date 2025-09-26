<?php

use Nilit\LaraBoilerCore\Models\File;
use Nilit\LaraBoilerCore\Models\Tenant;
use Nilit\LaraBoilerCore\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Create tenant for testing
    $tenant = Tenant::create([
        'id' => 'test-tenant-' . uniqid(),
        'name' => 'Test Tenant',
    ]);
    
    // Configure tenancy for the test
    tenancy()->initialize($tenant);
    
    // Configure storage disk for testing
    Storage::fake('tenant');
    
    // Create admin user with all permissions
    $adminUser = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    
    $adminRole = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $manageFilesPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'manage files']);
    
    $adminRole->givePermissionTo([
        $manageFilesPermission,
    ]);
    
    $adminUser->assignRole($adminRole);
    
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
    ]);
    $this->anotherTenant = $anotherTenant;
});

afterEach(function () {
    // Clean up: end tenancy
    tenancy()->end();
});

// File CRUD Tests
it('[file_crud_happy_path] allows uploading, viewing, and deleting files', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Upload a file
    $file = UploadedFile::fake()->create('document.pdf', 500);
    
    $response = $this->post(route('files.store'), [
        'file' => $file,
        'name' => 'Test Document',
        'description' => 'A test PDF document',
    ]);
    
    $response->assertStatus(302); // Redirect after upload
    
    // Check that the file was stored in the database
    $this->assertDatabaseHas('files', [
        'name' => 'Test Document',
        'description' => 'A test PDF document',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
    ]);
    
    // Get the uploaded file
    $uploadedFile = File::where('name', 'Test Document')->first();
    
    // Verify the file exists in storage
    Storage::disk('tenant')->assertExists($uploadedFile->path);
    
    // View the file details
    $response = $this->get(route('files.show', $uploadedFile->id));
    $response->assertStatus(200);
    $response->assertSee('Test Document');
    
    // Download the file
    $response = $this->get(route('files.download', $uploadedFile->id));
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    
    // Delete the file
    $response = $this->delete(route('files.destroy', $uploadedFile->id));
    $response->assertStatus(302); // Redirect after deletion
    
    // Check that the file was deleted from the database
    $this->assertSoftDeleted('files', [
        'id' => $uploadedFile->id,
    ]);
    
    // Check that the file was deleted from storage (if using soft deletes with physical file retention)
    // If physical files are not deleted immediately, this may not apply
    // Storage::disk('tenant')->assertMissing($uploadedFile->path);
});

it('[file_crud_validation] validates file uploads', function () {
    // Test as admin user
    $this->actingAs($this->adminUser);
    
    // Upload a file without required fields
    $file = UploadedFile::fake()->create('document.pdf', 500);
    
    $response = $this->post(route('files.store'), [
        'file' => $file,
        // Missing name
        'description' => 'A test PDF document',
    ]);
    
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['name']);
    
    // Upload a file with invalid type
    $invalidFile = UploadedFile::fake()->create('malicious.exe', 500);
    
    $response = $this->post(route('files.store'), [
        'file' => $invalidFile,
        'name' => 'Invalid File',
        'description' => 'A potentially malicious file',
    ]);
    
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['file']);
    
    // Upload a file that's too large
    $largeFile = UploadedFile::fake()->create('large.pdf', 20000); // 20MB (if limit is lower)
    
    $response = $this->post(route('files.store'), [
        'file' => $largeFile,
        'name' => 'Large File',
        'description' => 'A very large file',
    ]);
    
    $response->assertStatus(422); // Validation error
    $response->assertInvalid(['file']);
});

it('[file_crud_permission_denied] prevents unauthorized users from managing files', function () {
    // Test as regular user without permissions
    $this->actingAs($this->regularUser);
    
    // Try to upload a file
    $file = UploadedFile::fake()->create('document.pdf', 500);
    
    $response = $this->post(route('files.store'), [
        'file' => $file,
        'name' => 'Unauthorized Upload',
        'description' => 'This should fail',
    ]);
    
    $response->assertStatus(403); // Forbidden
    
    // Upload a file as admin
    $this->actingAs($this->adminUser);
    
    $this->post(route('files.store'), [
        'file' => $file,
        'name' => 'Admin Upload',
        'description' => 'This should work',
    ]);
    
    $uploadedFile = File::where('name', 'Admin Upload')->first();
    
    // Try to delete the file as regular user
    $this->actingAs($this->regularUser);
    
    $response = $this->delete(route('files.destroy', $uploadedFile->id));
    $response->assertStatus(403); // Forbidden
});

it('[file_policy_enforcement_happy_path] enforces file access control', function () {
    // Create a viewer role with permission to view but not modify files
    $viewerRole = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'file_viewer']);
    $viewFilesPermission = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'view files']);
    
    $viewerRole->givePermissionTo([
        $viewFilesPermission,
    ]);
    
    // Create a viewer user
    $viewerUser = User::factory()->create([
        'email' => 'viewer@example.com',
    ]);
    
    $viewerUser->assignRole($viewerRole);
    
    // Upload a file as admin
    $this->actingAs($this->adminUser);
    
    $file = UploadedFile::fake()->create('document.pdf', 500);
    $this->post(route('files.store'), [
        'file' => $file,
        'name' => 'Policy Test',
        'description' => 'Testing file policies',
    ]);
    
    $uploadedFile = File::where('name', 'Policy Test')->first();
    
    // Test as viewer user
    $this->actingAs($viewerUser);
    
    // Should be able to view the file
    $response = $this->get(route('files.show', $uploadedFile->id));
    $response->assertStatus(200);
    
    // Should be able to download the file
    $response = $this->get(route('files.download', $uploadedFile->id));
    $response->assertStatus(200);
    
    // Should NOT be able to delete the file
    $response = $this->delete(route('files.destroy', $uploadedFile->id));
    $response->assertStatus(403); // Forbidden
    
    // Should NOT be able to update the file
    $response = $this->put(route('files.update', $uploadedFile->id), [
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ]);
    $response->assertStatus(403); // Forbidden
});

it('[file_policy_enforcement_edge_cross_tenant] prevents cross-tenant file access', function () {
    // Upload a file in the first tenant as admin
    $this->actingAs($this->adminUser);
    
    $file = UploadedFile::fake()->create('document.pdf', 500);
    $this->post(route('files.store'), [
        'file' => $file,
        'name' => 'Tenant 1 File',
        'description' => 'A file in the first tenant',
    ]);
    
    $uploadedFile = File::where('name', 'Tenant 1 File')->first();
    $fileId = $uploadedFile->id;
    
    // Switch to the second tenant
    tenancy()->end();
    tenancy()->initialize($this->anotherTenant);
    
    // Create an admin user in the second tenant
    $adminUser2 = User::factory()->create([
        'email' => 'admin2@example.com',
    ]);
    
    $adminRole2 = \Nilit\LaraBoilerCore\Models\TenantRole::create(['name' => 'admin']);
    $manageFilesPermission2 = \Nilit\LaraBoilerCore\Models\TenantPermission::create(['name' => 'manage files']);
    
    $adminRole2->givePermissionTo([
        $manageFilesPermission2,
    ]);
    
    $adminUser2->assignRole($adminRole2);
    
    // Try to access the file from the first tenant
    $this->actingAs($adminUser2);
    
    $response = $this->get(route('files.show', $fileId));
    $response->assertStatus(404); // Not found in this tenant
    
    $response = $this->get(route('files.download', $fileId));
    $response->assertStatus(404); // Not found in this tenant
    
    $response = $this->delete(route('files.destroy', $fileId));
    $response->assertStatus(404); // Not found in this tenant
});
