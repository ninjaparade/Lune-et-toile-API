<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent when someone invites an email address. `$hasAccount` tells existing players they've
 * simply been added as a friend; everyone else is asked to sign up with this address.
 */
class InviteMail extends Mailable
{
    public function __construct(public User $inviter, public bool $hasAccount) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->inviter->name} wants to play chess with you");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invite');
    }
}
