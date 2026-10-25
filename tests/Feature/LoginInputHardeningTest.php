<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('sql injection payloads in the email field are rejected', function (string $payload) {
    Mail::fake();
    $user = User::factory()->create(['email' => 'admin@example.com']);

    Livewire::test(Login::class)
        ->set('email', $payload)
        ->call('sendLink')
        ->assertHasErrors('email')
        ->assertSet('linkSent', false);

    Mail::assertNothingSent();
    expect(User::count())->toBe(1)
        ->and($user->fresh())->not->toBeNull();
})->with([
    "admin@example.com' OR '1'='1",
    "admin@example.com'; DROP TABLE users; --",
    "' OR 1=1 --@example.com",
    'admin@example.com" OR ""="',
    'admin@example.com/**/UNION/**/SELECT/**/1',
    "admin'--@example.com",
    'admin@example.com; SELECT pg_sleep(5)',
    "admin\0@example.com",
    'admin@example.com OR 1=1',
]);

test('overlong email addresses are rejected', function () {
    Livewire::test(Login::class)
        ->set('email', str_repeat('a', 250).'@example.com')
        ->call('sendLink')
        ->assertHasErrors('email');
});

test('surrounding whitespace is trimmed before validation', function () {
    Mail::fake();

    Livewire::test(Login::class)
        ->set('email', '  admin@example.com  ')
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSet('linkSent', true);

    Mail::assertSentCount(1);
});

test('login link routes only accept well-formed tokens', function (string $method, string $token) {
    $this->call($method, '/anmelden/link/'.rawurlencode($token))->assertNotFound();

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with([
    ['GET', "x' OR '1'='1"],
    ['POST', "x' OR '1'='1"],
    ['POST', str_repeat('a', 63)],
    ['POST', str_repeat('a', 65)],
    ['POST', str_repeat('a', 60)."';--"],
]);
