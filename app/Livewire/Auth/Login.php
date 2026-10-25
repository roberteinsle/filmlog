<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Anmelden')]
class Login extends Component
{
    /**
     * Fehlversuche pro Minute, bevor der Login gesperrt wird.
     */
    private const int MAX_ATTEMPTS = 5;

    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = true;

    public function login(): void
    {
        $this->validate();

        $throttleKey = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($throttleKey).' Sekunden erneut versuchen.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'E-Mail oder Passwort ist falsch.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(route('tonight'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }

    private function throttleKey(): string
    {
        return Str::lower($this->email).'|'.request()->ip();
    }
}
