<?php

use Nilit\LaraBoilerCore\Models\User;

// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    
    $this->assertGuest();
});

test('users can not authenticate with wrong mail', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => 'wrong@mail.xxx',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});