<?php

use App\Http\Middleware\EnsureLoginIsFresh;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

test('the login page offers passkey first and the login link as fallback', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeInOrder(['Mit Passkey anmelden', 'Login-Link per E-Mail', 'Login-Link senden']);
});

test('guests get passkey login options', function () {
    $this->getJson(route('passkey.login-options'))
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge', 'rpId']]);
});

test('only the admin account may log in with a passkey', function (string $email, bool $isAllowed) {
    $user = User::factory()->create(['email' => $email]);
    $passkey = $user->passkeys()->create(['name' => 'iPhone', 'credential_id' => 'abc', 'credential' => []]);

    expect(Passkeys::allowsLogin(Request::create('/passkeys/login', 'POST'), $passkey->fresh()))->toBe($isAllowed);
})->with([
    'admin' => ['Admin@Example.com', true],
    'other' => ['fremd@example.com', false],
]);

test('a passkey login starts the seven day login period', function () {
    $this->actingAs(User::factory()->create());

    $response = app(PasskeyLoginResponse::class)->toResponse(
        Request::create('/passkeys/login', 'POST', server: ['HTTP_ACCEPT' => 'application/json']),
    );

    expect($response->getData(true)['redirect'])->toBe(url('/'))
        ->and(session(EnsureLoginIsFresh::SESSION_KEY))->toBe(now()->getTimestamp())
        ->and(session('auth.password_confirmed_at'))->toBe(now()->getTimestamp());
});

test('deleting a user removes their passkeys', function () {
    $user = User::factory()->create();
    $user->passkeys()->create(['name' => 'iPhone', 'credential_id' => 'abc', 'credential' => []]);

    $user->delete();

    expect(Passkey::count())->toBe(0);
});
