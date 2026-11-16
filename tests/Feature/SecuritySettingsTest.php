<?php

use App\Livewire\SecuritySettings;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

function createPasskey(User $user, string $name = 'iPhone'): Passkey
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => fake()->uuid(),
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ]);
}

beforeEach(function () {
    $this->user = actingAsLoggedInUser();
    session()->passwordConfirmed();
});

test('the settings page shows the security section with a hint when no passkey exists', function () {
    $this->get(route('settings'))
        ->assertOk()
        ->assertSeeLivewire(SecuritySettings::class)
        ->assertSee('Sicherheit')
        ->assertSee('Noch kein Passkey');
});

test('passkeys are listed', function () {
    createPasskey($this->user, 'MacBook');

    Livewire::test(SecuritySettings::class)
        ->assertSee('MacBook')
        ->assertDontSee('Noch kein Passkey');
});

test('a passkey can be deleted', function () {
    $passkey = createPasskey($this->user);

    Livewire::test(SecuritySettings::class)->call('deletePasskey', $passkey->id);

    expect(Passkey::count())->toBe(0);
});

test('passkeys of other users cannot be deleted', function () {
    $foreignPasskey = createPasskey(User::factory()->create());

    Livewire::test(SecuritySettings::class)
        ->call('deletePasskey', $foreignPasskey->id)
        ->assertNotFound();

    expect(Passkey::count())->toBe(1);
});

test('the authenticator app is set up after confirming a code', function () {
    $component = Livewire::test(SecuritySettings::class)->call('startTwoFactorSetup');
    $secret = session('two_factor.pending_secret');

    $component->assertSee(trim(chunk_split($secret, 4, ' ')))
        ->set('setupCode', app(Google2FA::class)->getCurrentOtp($secret))
        ->call('confirmTwoFactorSetup')
        ->assertHasNoErrors();

    $user = $this->user->fresh();
    $recoveryCodes = $component->get('recoveryCodes');

    expect($user->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->two_factor_secret)->toBe($secret)
        ->and($recoveryCodes)->toHaveCount(TwoFactorAuthentication::RECOVERY_CODE_COUNT)
        ->and($user->two_factor_recovery_codes)->not->toContain($recoveryCodes[0]);
});

test('a wrong setup code does not enable the authenticator app', function () {
    Livewire::test(SecuritySettings::class)
        ->call('startTwoFactorSetup')
        ->set('setupCode', '000000')
        ->call('confirmTwoFactorSetup')
        ->assertHasErrors('setupCode');

    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('the authenticator app can be removed', function () {
    app(TwoFactorAuthentication::class)->enable($this->user, app(TwoFactorAuthentication::class)->generateSecret());

    Livewire::test(SecuritySettings::class)->call('disableTwoFactor');

    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('changes require a recent confirmation', function () {
    $passkey = createPasskey($this->user);
    $this->travel(config('auth.password_timeout') + 60)->seconds();

    Livewire::test(SecuritySettings::class)
        ->assertSee('Mit Passkey bestätigen')
        ->call('deletePasskey', $passkey->id)
        ->assertHasErrors('security')
        ->call('startTwoFactorSetup')
        ->assertHasErrors('security');

    expect(Passkey::count())->toBe(1)
        ->and(session('two_factor.pending_secret'))->toBeNull();
});

test('registering a passkey requires a recent confirmation', function () {
    $this->getJson(route('passkey.registration-options'))->assertOk()->assertJsonStructure(['options' => ['challenge']]);

    $this->travel(config('auth.password_timeout') + 60)->seconds();

    $this->getJson(route('passkey.registration-options'))->assertStatus(423);
});
