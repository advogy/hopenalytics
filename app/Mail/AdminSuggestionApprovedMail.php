<?php

namespace App\Mail;

use App\Models\Church;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent only on approve — never on reject, per the user's explicit call ("klo tidak, tidak usah
 * ada notifikasi"). See Admin\AdminSuggestionController::approve(), fired after the DB
 * transaction commits so a mail never goes out for a request that ended up rolled back.
 */
class AdminSuggestionApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Church $church)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('admin_suggestions.mail_subject', ['church' => $this->church->name, 'app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-suggestion-approved',
            with: [
                'name' => $this->user->name,
                // Named churchName rather than church — Mailable::buildViewData() auto-injects
                // every public property (so $this->church, the whole model) as a view variable
                // keyed by its property name too; a same-named 'church' key here would collide
                // and get silently overwritten by that raw model, which is exactly what shipped:
                // the email body printed the full Church row as JSON instead of just its name.
                'churchName' => $this->church->name,
            ],
        );
    }
}
