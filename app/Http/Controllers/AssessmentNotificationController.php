<?php

namespace App\Http\Controllers;

use App\Models\AssessmentNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = AssessmentNotification::query()->where('recipient_id', $request->user()->id)->latest()->paginate(20)->through(fn ($item) => $item->only(['id', 'type', 'title', 'message', 'action_url', 'read_at', 'created_at']));

        return Inertia::render('notifications/index', ['notifications' => $notifications]);
    }

    public function read(Request $request, AssessmentNotification $notification): RedirectResponse
    {
        abort_unless($notification->recipient_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return $notification->action_url ? redirect($notification->action_url) : back();
    }
}
