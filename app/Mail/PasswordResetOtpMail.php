<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.password_reset_mail_subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-otp',
            with: [
                'name' => $this->user->name,
                'code' => $this->user->otp_code,
            ],
        );
    }
}
