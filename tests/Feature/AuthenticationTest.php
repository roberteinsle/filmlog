<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['tonight', 'search', 'lists', 'diary', 'review']);

test('the login page renders', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeLivewire(Login::class);
});

test('users can log in with valid credentials', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('tonight'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'falsch')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('login is throttled after five failed attempts', function () {
    $user = User::factory()->create();

    $component = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'falsch');

    foreach (range(1, 5) as $attempt) {
        $component->call('login');
    }

    $component->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
