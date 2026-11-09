<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditTrailController extends Controller
{
    public function __invoke(Request $request, AuditLogService $audit): Response
    {
        abort_unless(app(RolePermissionService::class)->can($request->user(), 'audit.view'), 403);
        $categories = ['attendance', 'payroll', 'banking', 'scheduling', 'account_status', 'contracts', 'documents', 'sanctions', 'approvals', 'permissions', 'backups', 'performance', 'offboarding', 'assessments', 'training', 'quality', 'general'];
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', Rule::in($categories)],
            'actor' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $query = ActivityLog::query()->with(['actor:id,name,username', 'subject:id,name,username'])
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('category', $value))
            ->when($filters['actor'] ?? null, fn ($q, $value) => $q->where('actor_id', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value))
            ->when($filters['search'] ?? null, function ($q, $value): void {
                $like = '%'.addcslashes(trim($value), '%_\\').'%';
                $q->where(fn ($q) => $q->where('action', 'like', $like)
                    ->orWhere('target_type', 'like', $like)
                    ->orWhereHas('actor', fn ($users) => $users->where('name', 'like', $like)->orWhere('username', 'like', $like))
                    ->orWhereHas('subject', fn ($users) => $users->where('name', 'like', $like)->orWhere('username', 'like', $like)));
            });

        return Inertia::render('audit-trail', [
            'logs' => $query->latest('id')->paginate(30)->withQueryString()->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'category' => $log->category,
                'action' => $log->action,
                'actor' => $log->actor?->only(['id', 'name', 'username']),
                'subject' => $log->subject?->only(['id', 'name', 'username']),
                'target' => ['type' => class_basename($log->target_type), 'id' => $log->target_id],
                'metadata' => $audit->sanitize($log->metadata ?? []),
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ]),
            'actors' => User::query()->whereIn('id', ActivityLog::query()->whereNotNull('actor_id')->select('actor_id'))->orderBy('name')->get(['id', 'name', 'username']),
            'categories' => $categories,
            'filters' => $filters,
            'summary' => [
                'today' => ActivityLog::where('created_at', '>=', now()->subDay())->count(),
                'week' => ActivityLog::where('created_at', '>=', now()->subDays(7))->count(),
                'total' => ActivityLog::count(),
            ],
        ]);
    }
}
