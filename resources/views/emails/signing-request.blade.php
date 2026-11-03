<x-mail::message>
# Please sign: {{ $document->title }}

Hello {{ $signer->name }},

**{{ $document->owner->name }}** ({{ $document->owner->email }}) has sent you a document to review and sign electronically.

@if ($document->message)
<x-mail::panel>
{{ $document->message }}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
Review & Sign
</x-mail::button>

This link is personal to you — please do not forward this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
