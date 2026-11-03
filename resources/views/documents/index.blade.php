@extends('layouts.app')
@section('title', 'Documents')
@section('content')
<div class="row between" style="margin-bottom:16px">
    <div><h1>Documents</h1><div class="muted">Everything you've sent for signature.</div></div>
    <a href="{{ route('documents.create') }}" class="btn btn-primary">+ New document</a>
</div>

<div class="stats">
    <div class="stat"><span class="muted small">Drafts</span><b>{{ $counts['draft'] ?? 0 }}</b></div>
    <div class="stat"><span class="muted small">Awaiting signature</span><b>{{ $counts['sent'] ?? 0 }}</b></div>
    <div class="stat"><span class="muted small">Completed</span><b>{{ $counts['completed'] ?? 0 }}</b></div>
    <div class="stat"><span class="muted small">Declined / voided</span><b>{{ ($counts['declined'] ?? 0) + ($counts['voided'] ?? 0) }}</b></div>
</div>

<div class="tabs">
    <a href="{{ route('documents.index') }}" class="{{ ! $status ? 'active' : '' }}">All</a>
    @foreach (['draft' => 'Drafts', 'sent' => 'Awaiting', 'completed' => 'Completed', 'declined' => 'Declined', 'voided' => 'Voided'] as $key => $label)
        <a href="{{ route('documents.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card" style="padding:0 0 4px">
    @if ($documents->isEmpty())
        <div class="empty">
            <p>No documents yet.</p>
            <a href="{{ route('documents.create') }}" class="btn btn-primary">Upload your first PDF</a>
        </div>
    @else
        <table>
            <thead><tr><th style="padding-left:20px">Title</th><th>Status</th><th>Progress</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            @foreach ($documents as $doc)
                <tr>
                    <td style="padding-left:20px"><a href="{{ $doc->isDraft() ? route('documents.edit', $doc) : route('documents.show', $doc) }}"><b>{{ $doc->title }}</b></a><div class="small muted">{{ $doc->original_name }} · {{ $doc->page_count }} page(s)</div></td>
                    <td><span class="badge badge-{{ $doc->statusColor() }}">{{ $doc->status }}</span></td>
                    <td>
                        @if ($doc->signers_count)
                            <div class="progress"><span style="width:{{ round($doc->signed_count / $doc->signers_count * 100) }}%"></span></div>
                            <span class="small muted">{{ $doc->signed_count }}/{{ $doc->signers_count }} signed</span>
                        @else <span class="small muted">No signers</span> @endif
                    </td>
                    <td class="small muted">{{ $doc->updated_at->diffForHumans() }}</td>
                    <td style="text-align:right;padding-right:16px">
                        @if ($doc->status === 'completed')<a class="btn btn-sm" href="{{ route('documents.download', $doc) }}">Download</a>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
<div style="margin-top:12px">{{ $documents->links() }}</div>
@endsection
