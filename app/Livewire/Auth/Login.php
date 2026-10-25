<?php

namespace App\Livewire\Auth;

use App\Services\LoginLinks;
use Illuminate\Contracts\View\View;
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
     * Angeforderte Links pro Adresse und IP innerhalb von zehn Minuten.
     */
    private const int MAX_REQUESTS = 3;

    #[Validate('required|string|email')]
    public string $email = '';

    public bool $linkSent = false;

    public function sendLink(LoginLinks $loginLinks): void
    {
        $this->validate();

        $throttleKey = 'login-link|'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_REQUESTS)) {
            throw ValidationException::withMessages([
                'email' => 'Zu viele Anfragen. Bitte in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' Minuten erneut versuchen.',
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        $loginLinks->send($this->email);

        $this->linkSent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
