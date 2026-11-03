@extends('layouts.app')
@section('title', 'Prepare · '.$document->title)
@section('content')
<form method="POST" action="{{ route('documents.update', $document) }}" id="setup-form">
    @csrf @method('PUT')
    <input type="hidden" name="setup" id="setup">
    <div class="row between" style="margin-bottom:16px">
        <div>
            <span class="badge badge-gray">Draft</span>
            <h1 style="margin-top:4px">Prepare document</h1>
        </div>
        <div class="row">
            <button type="button" class="btn btn-danger" onclick="if(confirm('Delete this draft?')) document.getElementById('delete-form').submit()">Delete</button>
            <button class="btn" name="action" value="save">Save draft</button>
            <button class="btn btn-primary" name="action" value="send">Send for signature →</button>
        </div>
    </div>

    <div class="workspace editor">
        <aside class="sidebar">
            <div class="card">
                <div class="field"><label for="title">Title</label><input type="text" id="title" name="title" value="{{ old('title', $document->title) }}" required></div>
                <div class="field"><label for="message">Message to signers <span class="muted small">(optional)</span></label><textarea id="message" name="message" rows="3">{{ old('message', $document->message) }}</textarea></div>
                <input type="hidden" name="sequential" value="0">
                <label class="check small"><input type="checkbox" name="sequential" id="sequential" value="1" @checked(old('sequential', $document->sequential))> Sign in order (signers are e-mailed one after another)</label>
            </div>
            <div class="card">
                <div class="row between"><h2 style="margin:0">1. Signers</h2></div>
                <div id="signer-list" style="margin-top:10px"></div>
                <div class="row">
                    <button type="button" class="btn btn-sm" id="add-signer">+ Add signer</button>
                    <button type="button" class="btn btn-sm" id="add-me">+ Add me</button>
                </div>
            </div>
            <div class="card palette">
                <h2>2. Place fields</h2>
                <p class="small muted" id="palette-hint">Add a signer first.</p>
                <p class="small muted">Pick a field, then click on the page. Fields are assigned to the highlighted signer. Hold <kbd>Shift</kbd> to place several.</p>
                @foreach (['signature' => '✍ Signature', 'initials' => 'Initials', 'name' => 'Full name', 'date' => 'Date signed', 'text' => 'Text box'] as $type => $label)
                    <button type="button" class="btn" data-type="{{ $type }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="card">
                <h2>Field settings</h2>
                <div id="field-options"></div>
            </div>
        </aside>
        <section id="pages" class="pages"></section>
    </div>
</form>
<form method="POST" action="{{ route('documents.destroy', $document) }}" id="delete-form">@csrf @method('DELETE')</form>
@endsection

@push('scripts')
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script src="{{ asset('js/pdf-viewer.js') }}"></script>
<script>
window.EDITOR = {
    pdfUrl: @json(route('documents.file', $document)),
    setup: @json(is_array(old('setup')) ? old('setup') : $setup),
    me: @json(['name' => auth()->user()->name, 'email' => auth()->user()->email]),
};
</script>
<script src="{{ asset('js/editor.js') }}"></script>
@endpush
