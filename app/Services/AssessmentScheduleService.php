<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AssessmentScheduleService
{
    private const TIMEZONE = 'Asia/Manila';

    public function __construct(private AssessmentNotificationService $notifications) {}

    /** @return array<string, int> */
    public function process(): array
    {
        $now = now();
        $published = 0;
        Assessment::query()->where('status', 'draft')->whereNotNull('publish_at')->where('publish_at', '<=', $now)
            ->orderBy('id')->chunkById(200, function ($assessments) use (&$published, $now): void {
                foreach ($assessments as $assessment) {
                    $changed = Assessment::query()->whereKey($assessment->id)->where('status', 'draft')->update(['status' => 'published', 'published_at' => $now, 'updated_at' => $now]);
                    if ($changed) {
                        $published++;
                        DB::table('assessment_activity_logs')->insert(['actor_id' => null, 'action' => 'Assessment Published Automatically', 'target_type' => Assessment::class, 'target_id' => $assessment->id, 'metadata' => json_encode(['publish_at' => $assessment->publish_at?->toIso8601String()]), 'created_at' => $now]);
                    }
                }
            });

        $counts = ['published' => $published, 'available' => 0, 'due_soon' => 0, 'due_today' => 0, 'overdue' => 0];
        AssessmentAssignment::query()->with('assessment:id,title')->whereNotIn('status', ['passed', 'failed', 'pending_review'])
            ->where(fn ($q) => $q->whereNotNull('available_from')->orWhereNotNull('due_date')->orWhereHas('assessment', fn ($assessment) => $assessment->whereNotNull('available_at')->orWhereNotNull('due_at')))
            ->orderBy('id')->chunkById(200, function ($assignments) use (&$counts, $now): void {
                foreach ($assignments as $assignment) {
                    $available = $assignment->effectiveAvailableAt();
                    $due = $assignment->effectiveDueAt();
                    if ($available?->lte($now) && $assignment->assigned_at->lt($available->copy()->subMinutes(15))) {
                        $this->notifications->create($assignment, 'available', 'Assessment Now Available', "{$assignment->assessment->title} is ready to take.");
                        $counts['available']++;
                    }
                    if (! $due) {
                        continue;
                    }
                    if ($due->lte($now)) {
                        $this->notifications->create($assignment, 'overdue', 'Assessment Overdue', "{$assignment->assessment->title} was due {$this->display($due)}. Contact your supervisor if you need assistance.");
                        $counts['overdue']++;
                    } elseif ($due->copy()->setTimezone(self::TIMEZONE)->isSameDay(now(self::TIMEZONE))) {
                        $this->notifications->create($assignment, 'due_today', 'Assessment Due Today', "{$assignment->assessment->title} is due today at {$due->copy()->setTimezone(self::TIMEZONE)->format('g:i A')}.");
                        $counts['due_today']++;
                    } elseif ($due->lte($now->copy()->addDay())) {
                        $this->notifications->create($assignment, 'due_soon', 'Assessment Due Soon', "{$assignment->assessment->title} is due {$this->display($due)}.");
                        $counts['due_soon']++;
                    }
                }
            });

        return $counts;
    }

    private function display(CarbonInterface $date): string
    {
        return $date->copy()->setTimezone(self::TIMEZONE)->format('M j, Y \a\t g:i A');
    }
}
