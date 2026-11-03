<?php

namespace App\Services;

use App\Mail\DocumentCompleted;
use App\Mail\DocumentDeclined;
use App\Mail\SigningRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Signer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SigningWorkflow
{
    public function __construct(protected PdfService $pdf) {}

    public function send(Document $document): void
    {
        $document->update(['status' => 'sent', 'sent_at' => now()]);
        AuditLog::record($document, 'sent', 'Document sent for signature by '.$document->owner->name.' ('.$document->owner->email.')');

        $this->notifyActiveSigners($document->fresh('signers'));
    }

    /** E-mail every signer whose turn it is and who has not been notified yet. */
    public function notifyActiveSigners(Document $document): void
    {
        foreach ($document->activeSigners() as $signer) {
            if ($signer->notified_at) {
                continue;
            }
            $this->mailSigner($signer);
        }
    }

    public function remind(Signer $signer): void
    {
        $this->mailSigner($signer, reminder: true);
    }

    protected function mailSigner(Signer $signer, bool $reminder = false): void
    {
        Mail::to($signer->email, $signer->name)->send(new SigningRequest($signer, $reminder));
        $signer->update(['notified_at' => now()]);
        AuditLog::record(
            $signer->document,
            $reminder ? 'reminded' : 'emailed',
            ($reminder ? 'Reminder e-mailed to ' : 'Signing request e-mailed to ').$signer->name.' ('.$signer->email.')',
        );
    }

    /**
     * Persist a signer's field values. $values maps field id => string value
     * (a PNG data URL for signature / initials fields).
     */
    public function sign(Signer $signer, array $values): void
    {
        $document = $signer->document;
        $request = request();

        DB::transaction(function () use ($signer, $values, $document, $request) {
            foreach ($signer->fields as $field) {
                $value = $values[$field->id] ?? null;

                if ($field->type === 'date') {
                    $value = now()->format('Y-m-d');
                } elseif ($field->type === 'name') {
                    $value = $signer->name;
                } elseif ($field->isImage() && $value) {
                    $value = $this->storeSignatureImage($document, $signer, $value);
                }

                $field->update(['value' => $value]);
            }

            $signer->update([
                'status' => 'signed',
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            AuditLog::record($document, 'signed', 'Signed by '.$signer->name.' ('.$signer->email.')', $signer);
        });

        $document = $document->fresh('signers');

        if ($document->signers->every(fn ($s) => $s->status === 'signed')) {
            $this->complete($document);
        } else {
            $this->notifyActiveSigners($document);
        }
    }

    public function decline(Signer $signer, ?string $reason): void
    {
        $document = $signer->document;
        $request = request();

        $signer->update([
            'status' => 'declined',
            'decline_reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
        $document->update(['status' => 'declined']);

        AuditLog::record($document, 'declined', 'Declined by '.$signer->name.' ('.$signer->email.')'.($reason ? ': '.$reason : ''), $signer);

        Mail::to($document->owner->email, $document->owner->name)->send(new DocumentDeclined($signer));
    }

    public function complete(Document $document): void
    {
        AuditLog::record($document, 'completed', 'All signers have signed. Document completed.');

        $path = $this->pdf->buildSignedPdf($document->fresh());
        $hash = hash('sha256', Storage::disk('local')->get($path));

        $document->update([
            'status' => 'completed',
            'completed_at' => now(),
            'signed_path' => $path,
            'signed_hash' => $hash,
        ]);

        Mail::to($document->owner->email, $document->owner->name)
            ->send(new DocumentCompleted($document, route('documents.show', $document)));

        foreach ($document->signers as $signer) {
            Mail::to($signer->email, $signer->name)
                ->send(new DocumentCompleted($document, route('sign.show', $signer->token)));
        }
    }

    protected function storeSignatureImage(Document $document, Signer $signer, string $dataUrl): string
    {
        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        $path = 'signatures/'.$document->id.'/'.$signer->id.'-'.bin2hex(random_bytes(8)).'.png';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
