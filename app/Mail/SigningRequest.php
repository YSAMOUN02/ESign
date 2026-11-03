<?php

namespace App\Mail;

use App\Models\Signer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SigningRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Signer $signer, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $doc = $this->signer->document;
        $prefix = $this->reminder ? 'Reminder: ' : '';

        return new Envelope(subject: $prefix.$doc->owner->name.' requests your signature on "'.$doc->title.'"');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.signing-request', with: [
            'document' => $this->signer->document,
            'url' => $this->signer->signingUrl(),
        ]);
    }
}
