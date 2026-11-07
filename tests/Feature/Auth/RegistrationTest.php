<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// RegistrationTest

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register');
});

test('new users can register', function () {
    $component = Volt::test('pages.auth.register')
        ->set('name', 'test')
        ->set('firstname', 'Test')
        ->set('lastname', 'User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $component->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::with('profile')->where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('test');
    expect($user->fullname)->toBe('Test User');
});

test('registration route is not registered in production', function () {
    $this->app['env'] = 'production';

    $router = $this->app['router'];
    $router->setRoutes(new RouteCollection);
    require base_path('routes/auth.php');
    $router->getRoutes()->refreshNameLookups();

    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('login'))->toBeTrue();

    $this->get('/register')->assertNotFound();
});
