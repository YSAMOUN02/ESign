@extends('layouts.app')
@section('title', $document->title)
@section('content')
<div class="row between" style="margin-bottom:16px">
    <div>
        <span class="badge badge-{{ $document->statusColor() }}">{{ $document->status }}</span>
        <h1 style="margin-top:4px">{{ $document->title }}</h1>
        <div class="muted small">{{ $document->original_name }} · {{ $document->page_count }} page(s) · created {{ $document->created_at->toDayDateTimeString() }}</div>
    </div>
    <div class="row">
        @if ($document->status === 'completed')
            <a href="{{ route('documents.download', $document) }}" class="btn btn-success">⬇ Download signed PDF</a>
        @endif
        <a href="{{ route('documents.file', $document) }}" target="_blank" class="btn">View original</a>
        @if ($document->status === 'sent')
            <form method="POST" action="{{ route('documents.void', $document) }}" onsubmit="return confirm('Void this document? Signers will no longer be able to sign.')">@csrf<button class="btn btn-danger">Void</button></form>
        @endif
        <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Permanently delete this document and its audit trail?')">@csrf @method('DELETE')<button class="btn btn-danger">Delete</button></form>
    </div>
</div>

<div class="grid grid-2">
    <div>
        <div class="card">
            <h2>Signers</h2>
            <table>
                <thead><tr><th>Signer</th>@if($document->sequential)<th>Order</th>@endif<th>Status</th><th>Signed</th><th></th></tr></thead>
                <tbody>
                @php($active = $document->activeSigners()->pluck('id'))
                @foreach ($document->signers as $signer)
                    <tr>
                        <td><b>{{ $signer->name }}</b><div class="small muted">{{ $signer->email }}</div></td>
                        @if($document->sequential)<td>{{ $signer->order }}</td>@endif
                        <td>
                            @php($c = ['pending' => 'gray', 'viewed' => 'amber', 'signed' => 'green', 'declined' => 'red'][$signer->status])
                            <span class="badge badge-{{ $c }}">{{ $signer->status }}</span>
                            @if ($signer->decline_reason)<div class="small muted">“{{ $signer->decline_reason }}”</div>@endif
                        </td>
                        <td class="small muted">{{ $signer->signed_at?->toDayDateTimeString() ?? '—' }}</td>
                        <td style="text-align:right">
                            @if ($document->status === 'sent' && $active->contains($signer->id))
                                <form method="POST" action="{{ route('documents.remind', [$document, $signer]) }}" style="display:inline">@csrf<button class="btn btn-sm">Send reminder</button></form>
                                <button type="button" class="btn btn-sm" data-url="{{ $signer->signingUrl() }}" onclick="navigator.clipboard.writeText(this.dataset.url);this.textContent='Copied!'">Copy link</button>
                            @endif
                            @if ($document->status === 'sent' && ! $signer->hasFinished())
                                <button type="button" class="btn btn-sm" data-change-email="{{ route('documents.signer-email', [$document, $signer]) }}" data-email="{{ $signer->email }}" data-name="{{ $signer->name }}" onclick="openEmailModal(this)">Change email</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>Integrity</h2>
            <div class="small"><b>Original SHA-256</b><div class="mono muted">{{ $document->original_hash }}</div></div>
            @if ($document->signed_hash)
                <div class="small" style="margin-top:8px"><b>Signed PDF SHA-256</b><div class="mono muted">{{ $document->signed_hash }}</div></div>
            @endif
        </div>
    </div>
    <div class="card">
        <h2>Audit trail</h2>
        <ul class="timeline">
            @foreach ($document->auditLogs->reverse() as $log)
                <li>
                    <div>{{ $log->description }}</div>
                    <div class="muted">{{ $log->created_at->toDayDateTimeString() }}@if($log->ip_address) · {{ $log->ip_address }}@endif</div>
                </li>
            @endforeach
        </ul>
    </div>
</div>

<div class="modal-bg" id="email-modal">
    <form class="modal" method="POST" id="email-form">
        @csrf @method('PUT')
        <h2>Change email for <span id="email-modal-name"></span></h2>
        <p class="small muted">The link already sent stops working. A new signing link goes to the new address, right away or when it is this signer's turn.</p>
        <div class="field"><label for="new-email">New email</label><input type="email" id="new-email" name="email" maxlength="255" required></div>
        <div class="row" style="justify-content:flex-end">
            <button type="button" class="btn" onclick="document.getElementById('email-modal').classList.remove('open')">Cancel</button>
            <button class="btn btn-primary">Save &amp; resend</button>
        </div>
    </form>
</div>
<script>
function openEmailModal(button) {
    document.getElementById('email-form').action = button.dataset.changeEmail;
    document.getElementById('email-modal-name').textContent = button.dataset.name;
    const input = document.getElementById('new-email');
    input.value = button.dataset.email;
    document.getElementById('email-modal').classList.add('open');
    input.select();
}
</script>
@endsection
