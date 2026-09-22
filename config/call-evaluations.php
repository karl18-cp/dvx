<?php

return [
    'recording_disk' => env('CALL_EVALUATION_RECORDING_DISK', 'local'),
    'max_recording_mb' => (int) env('CALL_EVALUATION_MAX_RECORDING_MB', 250),
    'max_document_mb' => (int) env('CALL_EVALUATION_MAX_DOCUMENT_MB', 20),
    'allowed_extensions' => ['mp3', 'wav', 'm4a'],
    'allowed_mime_types' => [
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav',
        'audio/mp4', 'audio/x-m4a', 'video/mp4',
    ],
];
