<?php

namespace App\Http\Controllers;

use App\Models\PublicUpdate;
use App\Models\PublicUpdateComment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicUpdateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('public-updates', [
            'updates' => PublicUpdate::query()
                ->withCount([
                    'comments',
                    'comments as pending_comments_count' => fn ($query) => $query->where('status', 'pending'),
                    'comments as approved_comments_count' => fn ($query) => $query->where('status', 'approved'),
                ])
                ->latest()
                ->paginate(15),
            'pendingComments' => PublicUpdateComment::query()
                ->where('status', 'pending')
                ->with('publicUpdate:id,title')
                ->oldest()
                ->paginate(20, ['*'], 'comments_page'),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authorizeAdmin($request);
        $data = $this->content($request);
        PublicUpdate::create([
            ...$data,
            'author_name' => $user->name,
            'created_by' => $user->id,
            'published_at' => $data['is_published'] ? now() : null,
        ]);

        return back()->with('status', $data['is_published'] ? 'The public update is now live.' : 'The update was saved as a draft.');
    }

    public function update(Request $request, PublicUpdate $publicUpdate): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->content($request);
        $publicUpdate->update([
            ...$data,
            'published_at' => $data['is_published'] ? ($publicUpdate->published_at ?? now()) : null,
        ]);

        return back()->with('status', 'The public update was updated.');
    }

    public function destroy(Request $request, PublicUpdate $publicUpdate): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $publicUpdate->delete();

        return back()->with('status', 'The public update and its comments were deleted.');
    }

    public function comment(Request $request, PublicUpdate $publicUpdate): RedirectResponse
    {
        abort_unless($publicUpdate->is_published && $publicUpdate->published_at?->lte(now()), 404);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'body' => ['required', 'string', 'min:2', 'max:1500'],
            'website' => ['nullable', 'max:0'],
        ]);
        unset($data['website']);
        PublicUpdateComment::firstOrCreate(['request_key' => $data['request_key']], [
            ...$data,
            'public_update_id' => $publicUpdate->id,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Thanks for commenting. It will appear after review.');
    }

    public function moderate(Request $request, PublicUpdateComment $comment): RedirectResponse
    {
        $user = $this->authorizeAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'hidden'])]]);
        DB::transaction(function () use ($comment, $data, $user): void {
            $comment->update([
                ...$data,
                'moderated_by' => $user->id,
                'moderated_at' => now(),
            ]);
        });

        return back()->with('status', $data['status'] === 'approved' ? 'The comment is now public.' : 'The comment was hidden.');
    }

    private function content(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'is_published' => ['required', 'boolean'],
        ]);
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->canAccessAccount() && $user->role === 'admin', 403);

        return $user;
    }
}
