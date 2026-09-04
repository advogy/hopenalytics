<?php

namespace App\Mail;

use App\Models\EmailBroadcast;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One recipient's copy of an EmailBroadcast (see Admin\EmailBroadcastController and
 * Jobs\SendBulkAnnouncementEmail, which sends this ->locale()'d to the recipient's own stored
 * language rather than the sending admin's — same reasoning as AdminSuggestionApprovedMail).
 */
class BulkAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmailBroadcast $broadcast, public User $recipient)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] '.$this->broadcast->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bulk-announcement',
            with: [
                // Named to avoid Mailable::buildViewData()'s auto-injection of every public
                // property colliding with an explicit view key of the same name — see
                // AdminSuggestionApprovedMail's own doc comment for the bug this avoids.
                'recipientName' => $this->recipient->name,
                'messageBody' => $this->broadcast->body,
            ],
        );
    }
}
