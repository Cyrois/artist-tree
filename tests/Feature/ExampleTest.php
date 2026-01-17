<?php

use App\Models\User;

test('unauthenticated users are redirected to login from home', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected to dashboard from home', function () {
    $user = User::factory()->create([
        'email' => 'test-'.uniqid().'@example.com',
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
