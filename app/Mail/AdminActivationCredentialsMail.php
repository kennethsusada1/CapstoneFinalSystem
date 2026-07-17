<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminActivationCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $activationUrl,
        public string $activationToken,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Smart L&D Employee ID Activation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-activation-credentials',
            text: 'emails.admin-activation-credentials-text',
        );
    }
}
