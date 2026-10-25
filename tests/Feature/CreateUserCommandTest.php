<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates the account', function () {
    $this->artisan('filmlog:user', ['email' => 'kino@example.com'])
        ->expectsQuestion('Passwort', 'ein-langes-passwort')
        ->expectsOutput('Konto kino@example.com angelegt.')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->email)->toBe('kino@example.com')
        ->and(Hash::check('ein-langes-passwort', $user->password))->toBeTrue();
});

test('it resets the password of an existing account', function () {
    $user = User::factory()->create(['email' => 'kino@example.com']);

    $this->artisan('filmlog:user', ['email' => 'kino@example.com'])
        ->expectsQuestion('Passwort', 'ein-neues-passwort')
        ->expectsOutput('Passwort für kino@example.com aktualisiert.')
        ->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and(Hash::check('ein-neues-passwort', $user->refresh()->password))->toBeTrue();
});

test('it rejects an invalid email address', function () {
    $this->artisan('filmlog:user', ['email' => 'kein-mailformat'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('it refuses to run without an interactive terminal', function () {
    $this->artisan('filmlog:user', ['email' => 'kino@example.com', '--no-interaction' => true])
        ->expectsOutputToContain('interaktiv')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
