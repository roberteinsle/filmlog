<?php

use App\Http\Middleware\EnsureLoginIsFresh;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Mail\LoginLinkMail;
use App\Models\User;
use App\Services\LoginLinks;
use App\Services\TwoFactorAuthentication;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/**
 * Admin-Konto mit eingerichteter Authenticator-App.
 *
 * @return array{0: User, 1: string}
 */
function adminWithTwoFactor(): array
{
    $user = User::factory()->create(['email' => 'admin@example.com', 'password' => null]);
    $twoFactor = app(TwoFactorAuthentication::class);
    $secret = $twoFactor->generateSecret();
    $twoFactor->enable($user, $secret);

    return [$user->fresh(), $secret];
}

function currentTotp(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

/**
 * Schickt einen Login-Link an die Admin-Adresse und liefert das Token aus der Mail.
 */
function mailedLoginToken(): string
{
    Mail::fake();
    app(LoginLinks::class)->send('admin@example.com');

    $token = null;
    Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $mail) use (&$token) {
        $token = basename(parse_url($mail->url, PHP_URL_PATH));

        return true;
    });

    return $token;
}

test('with an authenticator app the login link asks for a code instead of logging in', function () {
    adminWithTwoFactor();

    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]))
        ->assertRedirect(route('login.two-factor'));

    $this->assertGuest();
    $this->get(route('login.two-factor'))->assertOk()->assertSee('Code aus deiner Authenticator-App');
});

test('a valid code completes the login', function () {
    [$user, $secret] = adminWithTwoFactor();
    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', currentTotp($secret))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('tonight'));

    $this->assertAuthenticatedAs($user);
    expect(session(EnsureLoginIsFresh::SESSION_KEY))->toBeInt()
        ->and(session(TwoFactorChallenge::SESSION_KEY))->toBeNull();
});

test('a wrong code is rejected', function () {
    adminWithTwoFactor();
    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors('code');

    $this->assertGuest();
});

test('a code cannot be used twice', function () {
    [, $secret] = adminWithTwoFactor();
    $code = currentTotp($secret);

    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));
    Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasNoErrors();
    $this->post(route('logout'));

    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));
    Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasErrors('code');

    $this->assertGuest();
});

test('a recovery code works exactly once', function () {
    [$user] = adminWithTwoFactor();
    [$recoveryCode] = app(TwoFactorAuthentication::class)->regenerateRecoveryCodes($user);

    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));
    Livewire::test(TwoFactorChallenge::class)
        ->call('toggleRecoveryCode')
        ->set('code', strtoupper($recoveryCode))
        ->call('verify')
        ->assertHasNoErrors();
    $this->assertAuthenticatedAs($user);
    expect(app(TwoFactorAuthentication::class)->remainingRecoveryCodes($user->fresh()))->toBe(7);

    $this->post(route('logout'));
    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));
    Livewire::test(TwoFactorChallenge::class)
        ->call('toggleRecoveryCode')
        ->set('code', $recoveryCode)
        ->call('verify')
        ->assertHasErrors('code');
    $this->assertGuest();
});

test('code attempts are throttled', function () {
    [, $secret] = adminWithTwoFactor();
    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));

    $component = Livewire::test(TwoFactorChallenge::class);

    foreach (range(1, 5) as $attempt) {
        $component->set('code', '000000')->call('verify');
    }

    $component->set('code', currentTotp($secret))->call('verify')->assertHasErrors('code');
    $this->assertGuest();
});

test('the code step expires after ten minutes', function () {
    [, $secret] = adminWithTwoFactor();
    $this->post(route('login.link.confirm', ['token' => mailedLoginToken()]));

    $component = Livewire::test(TwoFactorChallenge::class);
    $this->travel(11)->minutes();

    $component->set('code', currentTotp($secret))->call('verify')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the code step cannot be opened without a login link', function () {
    adminWithTwoFactor();

    $this->get(route('login.two-factor'))->assertRedirect(route('login'));
});
