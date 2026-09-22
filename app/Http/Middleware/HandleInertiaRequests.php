<?php

namespace App\Http\Middleware;

use App\Models\AssessmentNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'assessment_notifications' => fn () => $request->user() ? [
                'unread_count' => AssessmentNotification::query()->where('recipient_id', $request->user()->id)->whereNull('read_at')->count(),
                'recent' => AssessmentNotification::query()->where('recipient_id', $request->user()->id)->latest()->limit(5)->get(['id', 'title', 'message', 'read_at', 'created_at']),
            ] : ['unread_count' => 0, 'recent' => []],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
