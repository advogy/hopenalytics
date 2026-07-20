<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.otp_mail_subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp-verification',
            with: [
                'name' => $this->user->name,
                'code' => $this->user->otp_code,
            ],
        );
    }
}
