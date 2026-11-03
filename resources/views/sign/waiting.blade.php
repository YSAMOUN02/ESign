@extends('layouts.signer')
@section('title', $document->title)
@section('container-class', 'narrow')
@section('content')
<div class="card" style="margin-top:40px;text-align:center">
    <div style="font-size:42px">⏳</div>
    <h1>Not your turn yet</h1>
    <p class="muted">“{{ $document->title }}” must be signed in order. We'll e-mail you when it's ready for your signature.</p>
</div>
@endsection
