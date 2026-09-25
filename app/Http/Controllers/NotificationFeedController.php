<?php

namespace App\Http\Controllers;

use App\Models\AssessmentNotification;
use App\Services\WorkplaceNotificationService;
use Illuminate\Http\Request;

class NotificationFeedController extends Controller
{
    private function user(Request $request)
    {
        $user = $request->user()->fresh();
        abort_unless($user->status === 'active', 403);

        return $user;
    }

    public function index(Request $request, WorkplaceNotificationService $service)
    {
        $user = $this->user($request);
        $service->sync($user);
        $query = AssessmentNotification::where('recipient_id', $user->id);

        return response()->json(['unread_count' => (clone $query)->whereNull('read_at')->count(), 'notifications' => $query->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')->latest('created_at')->latest('id')->paginate(20, ['id', 'type', 'title', 'message', 'read_at', 'created_at'])]);
    }

    public function read(Request $request, AssessmentNotification $notification)
    {
        $user = $this->user($request);
        abort_unless($notification->recipient_id === $user->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request)
    {
        $user = $this->user($request);
        $data = $request->validate(['ids' => ['required', 'array', 'max:20'], 'ids.*' => ['integer']]);
        AssessmentNotification::where('recipient_id', $user->id)->whereIn('id', $data['ids'])->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
