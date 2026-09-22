<?php

namespace App\Support;

class CoachingOptions
{
    public const TYPES = [
        'Assessment Review',
        'Call Handling Coaching',
        'Grammar Coaching',
        'Product Knowledge',
        'Performance Coaching',
        'Refresher Training',
        'General Coaching',
    ];

    public const STATUSES = ['open', 'follow_up_required', 'completed'];
}
