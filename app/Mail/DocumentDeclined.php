<?php

namespace App\Mail;

use App\Models\Signer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentDeclined extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Signer $signer) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->signer->name.' declined to sign "'.$this->signer->document->title.'"');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.document-declined', with: [
            'url' => route('documents.show', $this->signer->document),
        ]);
    }
}
