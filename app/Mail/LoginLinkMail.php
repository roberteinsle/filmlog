<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginLinkMail extends Mailable
{
    public function __construct(
        public string $url,
        public int $validMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Login-Link für Filmlog',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.login-link',
        );
    }
}
