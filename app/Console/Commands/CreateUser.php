<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

#[Signature('filmlog:user {email : E-Mail-Adresse für den Login} {--name=Filmlog : Anzeigename}')]
#[Description('Legt das Filmlog-Konto an oder setzt dessen Passwort neu')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('Ungültige E-Mail-Adresse.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Das Passwort wird interaktiv abgefragt – bitte mit Terminal ausführen (z. B. docker exec -it).');

            return self::FAILURE;
        }

        $password = password(
            label: 'Passwort',
            required: true,
            validate: fn (string $value): ?string => mb_strlen($value) < 12 ? 'Mindestens 12 Zeichen.' : null,
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => $password],
        );

        $this->info($user->wasRecentlyCreated ? "Konto {$email} angelegt." : "Passwort für {$email} aktualisiert.");

        return self::SUCCESS;
    }
}
