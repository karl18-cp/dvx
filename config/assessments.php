<?php

return [
    'media_disk' => env('ASSESSMENT_MEDIA_DISK', 'local'),
    'max_upload_kilobytes' => (int) env('ASSESSMENT_MEDIA_MAX_KB', 204800),
];
