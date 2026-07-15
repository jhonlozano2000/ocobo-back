<x-mail::message>
# {{ $subjectText }}

Adjunto encontrará el reporte programado generado automáticamente por el sistema OCOBO.

<x-mail::button :url="config('app.url')">
Ir a OCOBO
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
