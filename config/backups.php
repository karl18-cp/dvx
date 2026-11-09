<?php

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'directory' => env('BACKUP_DIRECTORY', 'system-backups'),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'schedule' => env('BACKUP_SCHEDULE', '02:00'),
    'timezone' => env('BACKUP_TIMEZONE', 'Asia/Manila'),
    'chunk_bytes' => 1024 * 1024,
    'sources' => [
        'profile_pictures' => ['disk' => 'local', 'path' => 'profile-photos', 'label' => 'Profile pictures'],
        'resumes' => ['disk' => 'local', 'path' => 'job-applications', 'label' => 'Applicant resumes'],
        'contracts' => ['disk' => 'local', 'path' => 'employee-contracts', 'label' => 'Employee contracts'],
        'call_recordings' => ['disk' => env('CALL_EVALUATION_RECORDING_DISK', 'local'), 'path' => 'call-evaluations', 'label' => 'Call recordings & evaluation files'],
        'accounting_attachments' => ['disk' => 'local', 'path' => 'accounting-documents', 'label' => 'Accounting attachments'],
    ],
];
