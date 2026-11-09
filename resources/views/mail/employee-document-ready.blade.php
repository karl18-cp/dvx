<x-mail::message>
# Your requested document is ready

Hello {{ $documentRequest->employee->name }},

Your {{ str($documentRequest->document_type)->replace('_', ' ') }} request has been completed. The document is attached to this email and remains available in your employee portal.

@if ($documentRequest->reviewer_notes)
**HR note:** {{ $documentRequest->reviewer_notes }}
@endif

Regards,
Divertex Global Strategies Corporation
</x-mail::message>
