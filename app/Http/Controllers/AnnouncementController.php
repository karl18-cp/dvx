<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    private function actor(Request $request, bool $manage = false): User
    {
        $user = $request->user()?->fresh();
        abort_unless($user && $user->status === 'active' && (! $manage || $user->role === 'admin'), 403);

        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->actor($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['search'] ?? '');

        return Inertia::render('announcements', [
            'announcements' => Announcement::query()->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like));
            })->latest('id')->paginate(10)->withQueryString(),
            'canManage' => $user->role === 'admin',
            'search' => $search,
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    private function content(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:10000']]);
    }

    public function store(Request $request)
    {
        $user = $this->actor($request, true);
        Announcement::create([...$this->content($request), 'user_id' => $user->id, 'author_name' => $user->name]);

        return to_route('announcements')->with('status', 'Announcement published.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->actor($request, true);
        $announcement->update($this->content($request));

        return back()->with('status', 'Announcement updated.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $this->actor($request, true);
        $announcement->delete();

        return to_route('announcements')->with('status', 'Announcement deleted.');
    }
}
