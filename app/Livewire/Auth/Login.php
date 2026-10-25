<?php

namespace App\Livewire\Auth;

use App\Services\LoginLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Anmelden')]
class Login extends Component
{
    /**
     * Angeforderte Links pro Adresse und IP innerhalb von zehn Minuten.
     */
    private const int MAX_REQUESTS = 3;

    /**
     * Erlaubte Zeichen für die Login-Adresse. Bewusst enger als RFC 5322:
     * Anführungszeichen, Semikolons, Leerzeichen und Kommentare (z. B. ' OR 1=1 --)
     * werden abgelehnt, bevor die Eingabe weiterverarbeitet wird.
     */
    public const string EMAIL_PATTERN = '/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/';

    public string $email = '';

    public bool $linkSent = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254', 'email:rfc,strict', 'regex:'.self::EMAIL_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.*' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
        ];
    }

    public function sendLink(LoginLinks $loginLinks): void
    {
        $this->email = trim($this->email);
        $this->validate();

        $throttleKey = 'login-link|'.Str::transliterate(Str::lower($this->email)).'|'.request()->ip();

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
