<?php

namespace App\Support;

class SkillPerformanceStatus
{
    public static function label(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'Strong',
            $percentage >= 80 => 'Good',
            $percentage >= 70 => 'Needs Improvement',
            default => 'Priority Coaching',
        };
    }
}
