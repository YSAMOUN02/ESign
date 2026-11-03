<x-mail::message>
# All parties have signed

"**{{ $document->title }}**" was completed on {{ $document->completed_at?->toDayDateTimeString() }} UTC.

The signed PDF, including the certificate of completion, is attached to this email.

<x-mail::button :url="$url">
View document
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
