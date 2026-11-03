@extends('layouts.signer')
@section('title', $document->title)
@section('container-class', 'narrow')
@section('content')
<div class="card" style="margin-top:40px;text-align:center">
    @if ($document->status === 'completed')
        <div style="font-size:42px">✅</div>
        <h1>Document completed</h1>
        <p class="muted">All parties have signed “{{ $document->title }}”.</p>
        <a href="{{ route('sign.download', $signer->token) }}" class="btn btn-success">⬇ Download signed PDF</a>
    @elseif ($signer->status === 'signed')
        <div style="font-size:42px">✍️</div>
        <h1>You've signed</h1>
        <p class="muted">Thanks, {{ $signer->name }}. We'll e-mail you the final copy once everyone has signed.</p>
    @elseif ($signer->status === 'declined')
        <div style="font-size:42px">🚫</div>
        <h1>You declined to sign</h1>
        <p class="muted">{{ $document->owner->name }} has been notified.</p>
    @elseif ($document->status === 'declined')
        <h1>Signing cancelled</h1>
        <p class="muted">Another signer declined this document, so it can no longer be signed.</p>
    @else
        <h1>Document unavailable</h1>
        <p class="muted">This document has been voided by the sender and can no longer be signed.</p>
    @endif
</div>
@endsection
