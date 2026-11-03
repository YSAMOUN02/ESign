@extends('layouts.signer')
@section('title', 'Sign · '.$document->title)
@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
@endpush
@section('nav')<span class="small muted">Signing as <b>{{ $signer->name }}</b> ({{ $signer->email }})</span>@endsection
@section('bar')
<div class="signbar"><div class="inner">
    <div style="flex:1;min-width:200px">
        <b>{{ $document->title }}</b>
        <div class="small muted">From {{ $document->owner->name }} · <span id="progress-text"></span></div>
    </div>
    <button type="button" class="btn" id="next-btn">Next field ↓</button>
    <button type="button" class="btn btn-danger" onclick="document.getElementById('decline-modal').classList.add('open')">Decline</button>
    <button type="button" class="btn btn-success" id="finish-btn">Finish</button>
</div></div>
@endsection
@section('content')
@if ($document->message)
    <div class="card" style="max-width:900px;margin:0 auto 16px"><b>Message from {{ $document->owner->name }}:</b><div style="white-space:pre-line">{{ $document->message }}</div></div>
@endif
<section id="pages" class="pages sign"></section>

<form method="POST" action="{{ route('sign.submit', $signer->token) }}" id="sign-form">
    @csrf
    <div id="values"></div>
    <div class="modal-bg" id="finish-modal">
        <div class="modal">
            <h2>Finish signing</h2>
            <p class="small muted">By clicking “Sign document” you agree that your electronic signature is the legal equivalent of your handwritten signature on this document, and you consent to do business electronically.</p>
            <label class="check"><input type="checkbox" name="consent" value="1" required> I agree to use electronic records and signatures.</label>
            <div class="row" style="justify-content:flex-end;margin-top:16px">
                <button type="button" class="btn" data-close>Cancel</button>
                <button class="btn btn-success">Sign document</button>
            </div>
        </div>
    </div>
</form>

<div class="modal-bg" id="sig-modal">
    <div class="modal">
        <div class="row between"><h2 style="margin:0" id="sig-title">Adopt your signature</h2>
            <div class="tabs" style="margin:0"><a href="#" data-mode="draw" class="active">Draw</a><a href="#" data-mode="type">Type</a></div></div>
        <div id="mode-draw" style="margin-top:12px">
            <canvas class="pad" id="pad"></canvas>
            <div class="row between small muted" style="margin-top:6px"><span>Draw with your mouse or finger</span><a href="#" id="clear-pad">Clear</a></div>
        </div>
        <div id="mode-type" style="margin-top:12px;display:none">
            <input type="text" id="typed" maxlength="60">
            <div class="type-preview" id="type-preview" style="margin-top:10px"></div>
        </div>
        <div class="row" style="justify-content:flex-end;margin-top:16px">
            <button type="button" class="btn" data-close>Cancel</button>
            <button type="button" class="btn btn-primary" id="adopt">Adopt & sign</button>
        </div>
    </div>
</div>

<div class="modal-bg" id="decline-modal">
    <form class="modal" method="POST" action="{{ route('sign.decline', $signer->token) }}">
        @csrf
        <h2>Decline to sign</h2>
        <p class="small muted">The sender will be notified and the document will be cancelled for all signers.</p>
        <div class="field"><label for="reason">Reason (optional)</label><textarea id="reason" name="reason" rows="3" maxlength="1000"></textarea></div>
        <div class="row" style="justify-content:flex-end">
            <button type="button" class="btn" data-close>Cancel</button>
            <button class="btn btn-danger" style="background:var(--red);color:#fff">Decline</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script src="{{ asset('js/pdf-viewer.js') }}"></script>
<script>
window.SIGNING = {
    pdfUrl: @json(route('sign.file', $signer->token)),
    fields: @json($fields),
    name: @json($signer->name),
    initials: @json($initials),
    today: @json(now()->format('Y-m-d')),
};
</script>
<script src="{{ asset('js/signing.js') }}"></script>
@endpush
