<?php

use App\Http\Middleware\EnsureLoginIsFresh;
use App\Livewire\Auth\Login;
use App\Mail\LoginLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * Fordert einen Login-Link für die Admin-Adresse an und liefert das Token aus der Mail.
 */
function requestLoginToken(): string
{
    Mail::fake();

    Livewire::test(Login::class)
        ->set('email', 'admin@example.com')
        ->call('sendLink');

    $token = null;
    Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $mail) use (&$token) {
        $token = basename(parse_url($mail->url, PHP_URL_PATH));

        return true;
    });

    return $token;
}

test('guests are redirected to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['tonight', 'search', 'lists', 'diary', 'review']);

test('the login page renders without password field', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeLivewire(Login::class)
        ->assertSee('Login-Link senden')
        ->assertDontSee('Passwort');
});

test('a login link is mailed to the admin address', function () {
    Mail::fake();

    Livewire::test(Login::class)
        ->set('email', 'Admin@Example.com')
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSet('linkSent', true);

    Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $mail) {
        return $mail->hasTo('Admin@Example.com')
            && $mail->validMinutes === 15
            && str_starts_with($mail->url, route('login').'/link/');
    });
});

test('no mail is sent for other addresses, but the response looks the same', function () {
    Mail::fake();

    Livewire::test(Login::class)
        ->set('email', 'fremd@example.com')
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSet('linkSent', true);

    Mail::assertNothingSent();
});

test('without a configured admin address nobody gets a login link', function (?string $adminEmail) {
    Mail::fake();
    config(['filmlog.admin_email' => $adminEmail]);

    Livewire::test(Login::class)
        ->set('email', 'admin@example.com')
        ->call('sendLink')
        ->assertSet('linkSent', true);

    Mail::assertNothingSent();
})->with(['missing' => [null], 'empty' => ['']]);

test('login link requests are throttled', function () {
    Mail::fake();

    $component = Livewire::test(Login::class)->set('email', 'admin@example.com');

    foreach (range(1, 3) as $attempt) {
        $component->call('sendLink')->assertHasNoErrors();
    }

    $component->call('sendLink')->assertHasErrors('email');
    Mail::assertSentCount(3);
});

test('opening the link only shows a confirmation and does not log in', function () {
    $token = requestLoginToken();

    $this->get(route('login.link', ['token' => $token]))
        ->assertOk()
        ->assertSee('Jetzt anmelden');

    $this->assertGuest();
});

test('confirming the link logs in and creates the admin account', function () {
    $token = requestLoginToken();

    $this->post(route('login.link.confirm', ['token' => $token]))
        ->assertRedirect(route('tonight'));

    $user = User::sole();
    expect($user->email)->toBe('admin@example.com')
        ->and($user->password)->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('a login link works only once', function () {
    $token = requestLoginToken();

    $this->post(route('login.link.confirm', ['token' => $token]));
    $this->post(route('logout'));

    $this->post(route('login.link.confirm', ['token' => $token]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
    $this->assertGuest();
});

test('a login link expires after 15 minutes', function () {
    $token = requestLoginToken();

    $this->travel(16)->minutes();

    $this->get(route('login.link', ['token' => $token]))->assertRedirect(route('login'));
    $this->post(route('login.link.confirm', ['token' => $token]))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('an unknown token is rejected', function () {
    $this->post(route('login.link.confirm', ['token' => str_repeat('x', 64)]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a login stays valid for 7 days', function () {
    $this->post(route('login.link.confirm', ['token' => requestLoginToken()]));

    $this->travel(6)->days();
    $this->get(route('tonight'))->assertOk();

    $this->travel(2)->days();
    $this->get(route('tonight'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a session without login timestamp is logged out', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tonight'))
        ->assertRedirect(route('login'));
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->withSession([EnsureLoginIsFresh::SESSION_KEY => now()->getTimestamp()])
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('the login mail contains the link and its validity', function () {
    $html = (new LoginLinkMail(url: 'https://filmlog.test/anmelden/link/abc', validMinutes: 15))->render();

    expect($html)->toContain('https://filmlog.test/anmelden/link/abc')
        ->toContain('15 Minuten gültig');
});
