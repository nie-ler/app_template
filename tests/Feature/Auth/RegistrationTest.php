<?php

use Nilit\LaraBoilerCore\Database\Seeders\PermissionSeeder;
// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);


test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register for test-Subscription', function () {
    // Zentrale Seeder, z. B. für Rollen
    $this->seed(PermissionSeeder::class);
    
    $response = $this->post('/register/test', [
        'name' => 'Test User',
        'email' => 'grenhm8pm7@example.com',
        'password' => 'GreNhM8PM7@N',
        'password_confirmation' => 'GreNhM8PM7@N',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});