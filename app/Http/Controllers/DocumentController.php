<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\Signer;
use App\Services\PdfService;
use App\Services\SigningWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');

        $documents = $user->documents()
            ->withCount(['signers', 'signers as signed_count' => fn ($q) => $q->where('status', 'signed')])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = $user->documents()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('documents.index', compact('documents', 'counts', 'status'));
    }

    public function create()
    {
        return view('documents.create');
    }

    public function store(Request $request, PdfService $pdf)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.config('esign.max_upload_kb')],
        ]);

        $file = $data['file'];
        $path = $file->store('documents/original', 'local');
        $absolute = Storage::disk('local')->path($path);

        try {
            $pages = $pdf->prepare($absolute);
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $document = $request->user()->documents()->create([
            'title' => $data['title'],
            'original_name' => $file->getClientOriginalName(),
            'original_path' => $path,
            'original_hash' => hash_file('sha256', $absolute),
            'page_count' => $pages,
        ]);

        AuditLog::record($document, 'created', 'Document "'.$document->original_name.'" uploaded by '.$request->user()->name);

        return redirect()->route('documents.edit', $document)->with('success', 'Document uploaded. Add signers and place fields.');
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);
        $document->load(['signers.fields', 'auditLogs']);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->authorize('update', $document);
        $document->load(['signers', 'fields']);

        $setup = [
            'signers' => $document->signers->map(fn ($s) => [
                'key' => 's'.$s->id, 'name' => $s->name, 'email' => $s->email, 'order' => $s->order,
            ])->values(),
            'fields' => $document->fields->map(fn ($f) => [
                'signer' => 's'.$f->signer_id, 'type' => $f->type, 'page' => $f->page,
                'x' => $f->x, 'y' => $f->y, 'width' => $f->width, 'height' => $f->height, 'required' => $f->required,
            ])->values(),
        ];

        return view('documents.edit', compact('document', 'setup'));
    }

    public function update(Request $request, Document $document, SigningWorkflow $workflow)
    {
        $this->authorize('update', $document);

        $request->merge(['setup' => json_decode((string) $request->input('setup'), true) ?? []]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'sequential' => ['boolean'],
            'action' => ['required', Rule::in(['save', 'send'])],
            'setup.signers' => ['array', 'max:20'],
            'setup.signers.*.key' => ['required', 'string', 'distinct'],
            'setup.signers.*.name' => ['required', 'string', 'max:255'],
            'setup.signers.*.email' => ['required', 'email', 'max:255', 'distinct:ignore_case'],
            'setup.signers.*.order' => ['required', 'integer', 'min:1', 'max:20'],
            'setup.fields' => ['array', 'max:500'],
            'setup.fields.*.signer' => ['required', 'string'],
            'setup.fields.*.type' => ['required', Rule::in(DocumentField::TYPES)],
            'setup.fields.*.page' => ['required', 'integer', 'min:1', 'max:'.$document->page_count],
            'setup.fields.*.x' => ['required', 'numeric', 'between:0,1'],
            'setup.fields.*.y' => ['required', 'numeric', 'between:0,1'],
            'setup.fields.*.width' => ['required', 'numeric', 'between:0.005,1'],
            'setup.fields.*.height' => ['required', 'numeric', 'between:0.005,1'],
            'setup.fields.*.required' => ['boolean'],
        ], [], ['setup.signers.*.email' => 'signer email', 'setup.signers.*.name' => 'signer name']);

        $signers = collect($data['setup']['signers'] ?? []);
        $fields = collect($data['setup']['fields'] ?? []);
        $keys = $signers->pluck('key');

        if ($fields->pluck('signer')->diff($keys)->isNotEmpty()) {
            throw ValidationException::withMessages(['setup' => 'A field is assigned to a signer that does not exist.']);
        }

        if ($data['action'] === 'send') {
            if ($signers->isEmpty()) {
                throw ValidationException::withMessages(['setup' => 'Add at least one signer before sending.']);
            }
            $without = $signers->filter(fn ($s) => ! $fields->contains(fn ($f) => $f['signer'] === $s['key'] && $f['type'] === 'signature'));
            if ($without->isNotEmpty()) {
                throw ValidationException::withMessages(['setup' => 'Every signer needs at least one signature field. Missing: '.$without->pluck('name')->join(', ')]);
            }
        }

        DB::transaction(function () use ($document, $data, $signers, $fields) {
            $document->update([
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'sequential' => (bool) ($data['sequential'] ?? false),
            ]);

            $document->signers()->delete(); // cascades to fields
            $map = [];
            foreach ($signers as $s) {
                $map[$s['key']] = $document->signers()->create([
                    'name' => $s['name'], 'email' => Str::lower($s['email']), 'order' => $s['order'],
                ])->id;
            }
            foreach ($fields as $f) {
                $document->fields()->create([
                    'signer_id' => $map[$f['signer']], 'type' => $f['type'], 'page' => $f['page'],
                    'x' => $f['x'], 'y' => $f['y'], 'width' => $f['width'], 'height' => $f['height'],
                    'required' => in_array($f['type'], ['date', 'name'], true) ? true : (bool) ($f['required'] ?? true),
                ]);
            }
        });

        if ($data['action'] === 'send') {
            $workflow->send($document->fresh());

            return redirect()->route('documents.show', $document)->with('success', 'Document sent for signature.');
        }

        return redirect()->route('documents.edit', $document)->with('success', 'Draft saved.');
    }

    public function file(Document $document)
    {
        $this->authorize('view', $document);

        return response()->file(Storage::disk('local')->path($document->original_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function download(Document $document)
    {
        $this->authorize('view', $document);
        abort_unless($document->signed_path, 404);

        return Storage::disk('local')->download($document->signed_path, Str::slug($document->title).'-signed.pdf');
    }

    public function remind(Document $document, Signer $signer, SigningWorkflow $workflow)
    {
        $this->authorize('manage', $document);
        abort_unless($signer->document_id === $document->id, 404);
        abort_unless($document->status === 'sent' && ! $signer->hasFinished() && $document->activeSigners()->contains('id', $signer->id), 422);

        $workflow->remind($signer);

        return back()->with('success', 'Reminder sent to '.$signer->email.'.');
    }

    public function void(Document $document)
    {
        $this->authorize('manage', $document);
        abort_unless($document->status === 'sent', 422);

        $document->update(['status' => 'voided']);
        AuditLog::record($document, 'voided', 'Document voided by '.auth()->user()->name);

        return back()->with('success', 'Document voided. Signing links no longer work.');
    }

    public function destroy(Document $document)
    {
        $this->authorize('manage', $document);

        $disk = Storage::disk('local');
        $disk->delete(array_filter([$document->original_path, $document->signed_path]));
        $disk->deleteDirectory('signatures/'.$document->id);
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Document deleted.');
    }
}
