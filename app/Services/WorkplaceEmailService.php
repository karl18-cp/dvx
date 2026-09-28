<?php

namespace App\Services;

use App\Jobs\SendWorkplaceEmail;
use App\Mail\WorkplaceUpdate;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentNotification;
use App\Models\ChatMessage;
use App\Models\EmployeeFormResponse;
use App\Models\User;
use App\Models\WorkplaceEmailDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WorkplaceEmailService
{
    public function start(): ?CarbonImmutable
    {
        return config('workplace-email.enabled') && config('workplace-email.start_at')
            ? CarbonImmutable::parse(config('workplace-email.start_at'), 'UTC') : null;
    }

    public function collect(): int
    {
        if (! $start = $this->start()) {
            return 0;
        }
        $created = 0;
        User::where('status', 'active')->chunkById(50, function ($users) use ($start, &$created) {
            foreach ($users as $user) {
                app(WorkplaceNotificationService::class)->sync($user);
                AssessmentNotification::where('recipient_id', $user->id)->where('created_at', '>=', $start)->chunkById(100, function ($items) use ($user, &$created) {
                    foreach ($items as $item) {
                        $created += $this->record($user, 'notification:'.$item->deduplication_key, 'notification', [], $item->id);
                    }
                });
            }
        });
        $this->dispatchPending();

        return $created;
    }

    public function collectChat(): int
    {
        if (! $start = $this->start()) {
            return 0;
        }
        $created = 0;
        User::where('status', 'active')->whereIn('role', ['admin', 'team_leader', 'agent'])->chunkById(50, function ($users) use (&$created) {
            foreach ($users as $user) {
                $messages = $this->unreadMessages($user);
                if ($last = (clone $messages)->max('id')) {
                    $created += $this->record($user, 'chat:'.$last, 'chat', ['last_id' => $last]);
                }
            }
        });
        $this->dispatchPending();

        return $created;
    }

    public function collectWeekly(): int
    {
        if (! $this->start()) {
            return 0;
        }
        $end = CarbonImmutable::now(config('workplace-email.timezone'))->startOfWeek()->utc();
        $created = 0;
        User::where('status', 'active')->whereIn('role', ['admin', 'team_leader', 'agent'])->chunkById(50, function ($users) use ($end, &$created) {
            foreach ($users as $user) {
                $created += $this->record($user, 'ranking:'.$end->toDateString(), 'ranking', ['end' => $end->toIso8601String()]);
            }
        });
        $this->dispatchPending();

        return $created;
    }

    private function record(User $user, string $key, string $kind, array $payload, ?int $notificationId = null): int
    {
        return (int) WorkplaceEmailDelivery::firstOrCreate(['event_key' => hash('sha256', $user->id.':'.$key)], [
            'recipient_id' => $user->id, 'notification_id' => $notificationId, 'kind' => $kind, 'payload' => $payload,
        ])->wasRecentlyCreated;
    }

    public function dispatchPending(): void
    {
        WorkplaceEmailDelivery::whereIn('status', ['pending', 'failed'])->where('attempts', '<', 3)
            ->where(fn ($q) => $q->whereNull('queued_at')->orWhere('queued_at', '<', now()->subMinutes(30)))
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    DB::transaction(function () use ($item) {
                        $delivery = WorkplaceEmailDelivery::whereKey($item->id)->lockForUpdate()->first();
                        if (! $delivery || ! in_array($delivery->status, ['pending', 'failed']) || ($delivery->queued_at && $delivery->queued_at->gt(now()->subMinutes(30)))) {
                            return;
                        }
                        $delivery->update(['queued_at' => now()]);
                        SendWorkplaceEmail::dispatch($delivery->id)->afterCommit();
                    });
                }
            });
    }

    public function deliver(int $id): void
    {
        if (! $this->start()) {
            return;
        }
        $source = WorkplaceEmailDelivery::find($id);
        $user = $source ? User::find($source->recipient_id) : null;
        // Recheck role/team access and corrected or deleted workplace alerts at delivery time.
        if ($source?->kind === 'notification' && $user?->status === 'active') {
            app(WorkplaceNotificationService::class)->sync($user);
        }
        $failed = false;
        DB::transaction(function () use ($id, &$failed) {
            $delivery = WorkplaceEmailDelivery::whereKey($id)->lockForUpdate()->first();
            if (! $delivery || in_array($delivery->status, ['sent', 'skipped']) || $delivery->attempts >= 3) {
                return;
            }
            $user = User::find($delivery->recipient_id);
            if (! $user || $user->status !== 'active' || ! $this->validEmail($user->email)) {
                $delivery->update(['status' => 'skipped', 'failure_reason' => 'inactive_or_missing_deliverable_email']);

                return;
            }
            $content = $this->content($delivery, $user);
            if (! $content) {
                $delivery->update(['status' => 'skipped', 'failure_reason' => 'no_longer_relevant']);

                return;
            }
            $delivery->attempts++;
            try {
                Mail::to($user->email)->send(new WorkplaceUpdate($user->name, $content[0], $content[1]));
                $delivery->fill(['status' => 'sent', 'sent_at' => now(), 'failure_reason' => null]);
            } catch (\Throwable $exception) {
                $delivery->fill(['status' => 'failed', 'failure_reason' => class_basename($exception)]);
                $failed = true;
            }
            $delivery->save();
        });
        if ($failed) {
            throw new \RuntimeException('Workplace email delivery failed; see delivery status for retry tracking.');
        }
    }

    private function validEmail(?string $email): bool
    {
        return $email && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! preg_match('/@(?:[^@]+\.)?(?:local|test|invalid|localhost|example\.com|example\.org|example\.net)$/i', $email);
    }

    private function content(WorkplaceEmailDelivery $delivery, User $user): ?array
    {
        if ($delivery->kind === 'notification') {
            $item = AssessmentNotification::whereKey($delivery->notification_id)->where('recipient_id', $user->id)->first();
            if (! $item || $item->created_at->lt($this->start())) {
                return null;
            }
            if ($item->assignment_id && ! AssessmentAssignment::whereKey($item->assignment_id)->where('employee_id', $user->id)->exists()) {
                return null;
            }

            return [$item->title, $item->message];
        }
        if (! in_array($user->role, ['admin', 'team_leader', 'agent'])) {
            return null;
        }
        if ($delivery->kind === 'ranking') {
            return $this->weeklyContent($user, CarbonImmutable::parse($delivery->payload['end']));
        }
        if ($delivery->kind === 'chat') {
            $messages = $this->unreadMessages($user)->where('id', '<=', $delivery->payload['last_id']);
            $count = (clone $messages)->count();

            return $count ? ['Unread DiverText messages', "You have {$count} unread message(s) in conversations available to your account. Open DiverText to read and reply.\n\nMessage contents are kept inside your portal."] : null;
        }

        return null;
    }

    private function unreadMessages(User $user)
    {
        $service = app(DiverTextService::class);
        $query = ChatMessage::whereIn('conversation_id', $service->visible($user)->select('chat_conversations.id'))
            ->where('created_at', '>=', $this->start())->where('created_at', '<=', now()->subMinutes(5));
        $service->unread($query, $user);

        return $query;
    }

    private function weeklyContent(User $user, CarbonImmutable $end): array
    {
        $start = $end->subWeek();
        $rankings = function ($before) use ($user) {
            return EmployeeFormResponse::whereNotNull('employee_id')->where('points', '>', 0)->where('created_at', '<', $before)
                ->when($user->role === 'team_leader', fn ($q) => $q->whereIn('employee_id', app(TeamLeaderWorkspaceService::class)->members($user)->select('users.id')))
                ->select('employee_id')->selectRaw('SUM(points) as total')->groupBy('employee_id')->orderByDesc('total')->orderBy('employee_id')->get();
        };
        $current = $rankings($end);
        $previous = $rankings($start);
        $earned = (int) EmployeeFormResponse::where('employee_id', $user->id)->where('created_at', '>=', $start)->where('created_at', '<', $end)->sum('points');
        $rank = $current->search(fn ($row) => $row->employee_id === $user->id);
        $oldRank = $previous->search(fn ($row) => $row->employee_id === $user->id);
        $range = $start->setTimezone(config('workplace-email.timezone'))->format('M j').'–'.$end->subSecond()->setTimezone(config('workplace-email.timezone'))->format('M j, Y');
        $body = "Week: {$range}\nPoints earned this week: {$earned}\n";
        $body .= $user->role === 'team_leader' ? 'The leaderboard below covers agents in your assigned teams.' : ($rank === false ? 'You have no ranking points in this leaderboard yet.' : 'Your position: #'.($rank + 1).' · Total points: '.$current[$rank]->total);
        if ($rank !== false && $oldRank !== false) {
            $body .= "\nPosition change: ".($oldRank === $rank ? 'unchanged' : (($oldRank > $rank ? 'up ' : 'down ').abs($oldRank - $rank)));
        }
        if (in_array($user->role, ['admin', 'team_leader'])) {
            $top = $current->take(5);
            $names = User::whereIn('id', $top->pluck('employee_id'))->pluck('name', 'id');
            $body .= "\n\n".($user->role === 'team_leader' ? 'Your teams — top 5' : 'Company top 5');
            foreach ($top as $index => $row) {
                $body .= "\n".($index + 1).'. '.($names[$row->employee_id] ?? 'Former employee').' — '.$row->total.' points';
            }
            if ($top->isEmpty()) {
                $body .= "\nNo ranking points yet.";
            }
        }

        return ['Your weekly ranking progress', $body];
    }
}
