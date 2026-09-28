<?php

return [
    'enabled' => (bool) env('WORKPLACE_EMAIL_ENABLED', false),
    // Required activation boundary: never email historical alerts on first deployment.
    'start_at' => env('WORKPLACE_EMAIL_START_AT'),
    'timezone' => 'Asia/Manila',
];
