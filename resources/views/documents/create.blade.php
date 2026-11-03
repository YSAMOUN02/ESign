@extends('layouts.app')
@section('title', 'New document')
@section('container-class', 'narrow')
@section('content')
<div class="card">
    <h1>Upload a document</h1>
    <p class="muted">Upload the PDF you need signed. Next you'll add signers and place fields.</p>
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="field"><label for="title">Title</label><input id="title" type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Employment contract" required></div>
        <div class="field"><label for="file">PDF file</label><input id="file" type="file" name="file" accept="application/pdf" required>
            <div class="small muted">Max {{ round(config('esign.max_upload_kb') / 1024) }} MB.</div></div>
        <button class="btn btn-primary btn-block">Upload & continue</button>
    </form>
</div>
<script>
document.getElementById('file').addEventListener('change', e => {
    const t = document.getElementById('title');
    if (!t.value && e.target.files[0]) t.value = e.target.files[0].name.replace(/\.pdf$/i, '');
});
</script>
@endsection
