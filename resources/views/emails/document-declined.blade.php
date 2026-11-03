<x-mail::message>
# Signature declined

**{{ $signer->name }}** ({{ $signer->email }}) declined to sign "**{{ $signer->document->title }}**".

@if ($signer->decline_reason)
<x-mail::panel>
{{ $signer->decline_reason }}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
View document
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
