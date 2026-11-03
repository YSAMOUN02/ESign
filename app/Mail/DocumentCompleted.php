<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Completed: "'.$this->document->title.'" has been signed by all parties');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.document-completed');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->document->signed_path)
                ->as($this->document->signedFileName())
                ->withMime('application/pdf'),
        ];
    }
}
