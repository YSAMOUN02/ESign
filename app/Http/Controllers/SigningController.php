<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Signer;
use App\Services\SigningWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SigningController extends Controller
{
    protected function signer(string $token): Signer
    {
        return Signer::where('token', $token)->with(['document.owner', 'document.signers', 'fields'])->firstOrFail();
    }

    public function show(Request $request, string $token)
    {
        $signer = $this->signer($token);
        $document = $signer->document;

        if ($document->status === 'completed') {
            return view('sign.finished', compact('signer', 'document'));
        }
        if (in_array($document->status, ['declined', 'voided', 'draft'], true) || $signer->hasFinished()) {
            return view('sign.finished', compact('signer', 'document'));
        }
        if (! $document->activeSigners()->contains('id', $signer->id)) {
            return view('sign.waiting', compact('signer', 'document'));
        }

        if ($signer->status === 'pending') {
            $signer->update(['status' => 'viewed', 'viewed_at' => now()]);
            AuditLog::record($document, 'viewed', 'Viewed by '.$signer->name.' ('.$signer->email.')', $signer);
        }

        $fields = $signer->fields->map(fn ($f) => [
            'id' => $f->id, 'type' => $f->type, 'page' => $f->page, 'required' => $f->required,
            'x' => $f->x, 'y' => $f->y, 'width' => $f->width, 'height' => $f->height,
        ])->values();

        $initials = collect(preg_split('/\s+/', trim($signer->name)))
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');

        return view('sign.show', compact('signer', 'document', 'fields', 'initials'));
    }

    public function file(string $token)
    {
        $signer = $this->signer($token);
        $document = $signer->document;
        abort_if(in_array($document->status, ['draft', 'voided'], true), 404);

        return response()->file(Storage::disk('local')->path($document->original_path), ['Content-Type' => 'application/pdf']);
    }

    public function download(string $token)
    {
        $signer = $this->signer($token);
        $document = $signer->document;
        abort_unless($document->status === 'completed' && $document->signed_path, 404);

        return Storage::disk('local')->download($document->signed_path, Str::slug($document->title).'-signed.pdf');
    }

    public function submit(Request $request, string $token, SigningWorkflow $workflow)
    {
        $signer = $this->signer($token);
        $this->ensureCanAct($signer);

        $request->validate([
            'consent' => ['accepted'],
            'values' => ['array'],
            'values.*' => ['nullable', 'string', 'max:500000'],
        ], ['consent.accepted' => 'You must agree to sign electronically.']);

        $values = $request->input('values', []);
        $errors = [];

        foreach ($signer->fields as $field) {
            $value = $values[$field->id] ?? null;
            if (in_array($field->type, ['date', 'name'], true)) {
                continue;
            }
            if ($field->required && blank($value)) {
                $errors[] = 'Please complete all required fields (page '.$field->page.').';
                continue;
            }
            if ($field->isImage() && filled($value) && ! $this->isValidPng($value)) {
                $errors[] = 'Invalid signature image.';
            }
            if ($field->type === 'text' && filled($value) && mb_strlen($value) > 255) {
                $errors[] = 'Text fields may not exceed 255 characters.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(['values' => array_values(array_unique($errors))]);
        }

        $workflow->sign($signer, $values);

        return redirect()->route('sign.show', $token)->with('success', 'Thank you! Your signature has been recorded.');
    }

    public function decline(Request $request, string $token, SigningWorkflow $workflow)
    {
        $signer = $this->signer($token);
        $this->ensureCanAct($signer);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $workflow->decline($signer, $data['reason'] ?? null);

        return redirect()->route('sign.show', $token);
    }

    protected function ensureCanAct(Signer $signer): void
    {
        abort_unless(
            $signer->document->status === 'sent'
            && ! $signer->hasFinished()
            && $signer->document->activeSigners()->contains('id', $signer->id),
            403,
            'This document can no longer be signed.'
        );
    }

    protected function isValidPng(string $dataUrl): bool
    {
        $prefix = 'data:image/png;base64,';
        if (! str_starts_with($dataUrl, $prefix)) {
            return false;
        }
        $binary = base64_decode(substr($dataUrl, strlen($prefix)), true);
        if ($binary === false || strlen($binary) > 2 * 1024 * 1024) {
            return false;
        }
        $info = @getimagesizefromstring($binary);

        return $info !== false && $info[2] === IMAGETYPE_PNG && $info[0] <= 3000 && $info[1] <= 3000;
    }
}
