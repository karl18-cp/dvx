<?php

return [
    'node_binary' => env('FACE_NODE_BINARY', PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/nodejs/node.exe' : 'node'),
    'model_version' => 'human-3.3.6-faceres',
    // Conservative starting thresholds; validate against your actual enrollment/camera conditions.
    'minimum_similarity' => 0.75,
    'minimum_liveness' => 0.75,
    'challenge_seconds' => 120,
];
