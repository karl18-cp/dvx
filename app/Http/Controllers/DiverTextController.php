<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\DiverTextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DiverTextController extends Controller
{
    public function __construct(private DiverTextService $chat) {}

    private function actor(Request $request): User
    {
        $user = $request->user()->fresh();
        abort_unless($user->status === 'active', 403);

        return $user;
    }

    private function authorizeRoom(User $user, ChatConversation $conversation): void
    {
        abort_unless($this->chat->visible($user)->whereKey($conversation->id)->exists(), 403);
    }

    public function index(Request $request)
    {
        return Inertia::render('divertext', ['initialRooms' => $this->chat->rooms($this->actor($request))]);
    }

    public function rooms(Request $request)
    {
        return response()->json($this->chat->rooms($this->actor($request)));
    }

    public function unread(Request $request)
    {
        $user = $this->actor($request);
        $query = ChatMessage::whereIn('conversation_id', $this->chat->visible($user)->select('chat_conversations.id'));
        $this->chat->unread($query, $user);

        return response()->json(['count' => $query->count()]);
    }

    public function people(Request $request)
    {
        $user = $this->actor($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['search'] ?? '');
        $people = $this->chat->people($user)->where('status', 'active')->where('id', '!=', $user->id)->when($search !== '', function ($q) use ($search) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('username', 'like', $like));
        })->orderBy('name')->limit(50)->get(['id', 'name', 'username', 'role', 'profile_photo_path']);

        return response()->json($people->map(fn ($person) => $person->only(['id', 'name', 'username', 'role', 'avatar'])));
    }

    public function start(Request $request)
    {
        $user = $this->actor($request);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        abort_if((int) $data['user_id'] === $user->id, 422, 'Choose another employee.');
        $peer = User::where('status', 'active')->findOrFail($data['user_id']);
        abort_unless($this->chat->people($user)->whereKey($peer->id)->exists(), 403);
        $ids = [$user->id, $peer->id];
        sort($ids);
        $room = ChatConversation::firstOrCreate(['conversation_key' => 'direct:'.implode(':', $ids)], ['type' => 'direct', 'first_user_id' => $ids[0], 'second_user_id' => $ids[1]]);

        return response()->json($this->chat->room($room, $user));
    }

    public function messages(Request $request, ChatConversation $conversation)
    {
        $user = $this->actor($request);
        $this->authorizeRoom($user, $conversation);
        $data = $request->validate(['before' => ['nullable', 'integer', 'min:1'], 'after' => ['nullable', 'integer', 'min:0']]);
        $query = $conversation->messages()->with('sender:id,name,profile_photo_path');
        $after = array_key_exists('after', $data) && $data['after'] !== null;
        if ($after) {
            $query->where('id', '>', $data['after'])->orderBy('id');
        } else {
            $query->when($data['before'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))->orderByDesc('id');
        }
        $rows = $query->limit(51)->get();
        $hasMore = $rows->count() > 50;
        $rows = $rows->take(50)->sortBy('id')->values();

        return response()->json(['messages' => $rows->map(fn ($message) => $this->message($message)), 'has_more' => $hasMore, 'room' => $this->chat->room($conversation, $user)]);
    }

    private function message(ChatMessage $message): array
    {
        return ['id' => $message->id, 'sender_id' => $message->sender_id, 'sender_name' => $message->sender?->name ?? $message->sender_name, 'avatar' => $message->sender?->avatar ?? '', 'body' => $message->body, 'created_at' => $message->created_at];
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $user = $this->actor($request);
        $this->authorizeRoom($user, $conversation);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'body' => ['required', 'string', 'max:4000']]);
        abort_unless($this->chat->room($conversation, $user)['can_send'], 422, 'This employee is no longer active.');
        $message = DB::transaction(function () use ($user, $conversation, $data) {
            ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $existing = ChatMessage::where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless($existing->sender_id === $user->id && $existing->conversation_id === $conversation->id, 403);

                return $existing;
            }

            return ChatMessage::create([...$data, 'conversation_id' => $conversation->id, 'sender_id' => $user->id, 'sender_name' => $user->name]);
        });

        return response()->json($this->message($message));
    }

    public function read(Request $request, ChatConversation $conversation)
    {
        $user = $this->actor($request);
        $this->authorizeRoom($user, $conversation);
        $data = $request->validate(['message_id' => ['required', 'integer']]);
        abort_unless($conversation->messages()->whereKey($data['message_id'])->exists(), 422);
        DB::transaction(function () use ($user, $conversation, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('chat_reads')->where('conversation_id', $conversation->id)->where('user_id', $user->id);
            $existing = $query->first();
            if ($existing) {
                $query->update(['last_read_message_id' => max($existing->last_read_message_id, $data['message_id']), 'updated_at' => now()]);
            } else {
                DB::table('chat_reads')->insert(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'last_read_message_id' => $data['message_id'], 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
