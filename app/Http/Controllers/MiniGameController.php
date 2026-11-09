<?php

namespace App\Http\Controllers;

use App\Models\MiniGameInvitation;
use App\Models\MiniGamePresence;
use App\Models\MiniGameRoom;
use App\Models\MiniGameRoomPlayer;
use App\Models\MiniGameStat;
use App\Models\User;
use App\Services\MiniGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MiniGameController extends Controller
{
    public function index(Request $request): Response
    {
        $this->player($request);

        return Inertia::render('mini-games');
    }

    public function snapshot(Request $request): JsonResponse
    {
        $user = $this->player($request);
        $this->touch($user);
        $cutoff = now()->subSeconds(20);
        $people = User::query()
            ->where('users.status', 'active')
            ->whereIn('users.role', ['agent', 'team_leader'])
            ->with('teamMembership.team:id,name')
            ->leftJoin('mini_game_presences', 'users.id', '=', 'mini_game_presences.user_id')
            ->where(fn ($query) => $query->where('users.id', $user->id)->orWhere('mini_game_presences.last_seen_at', '>=', $cutoff))
            ->orderByRaw('users.id = ? desc', [$user->id])
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.username', 'users.profile_photo_path', 'users.role', 'mini_game_presences.status as game_status', 'mini_game_presences.last_seen_at'])
            ->map(fn (User $person) => [
                'id' => $person->id, 'name' => $person->name, 'username' => $person->username, 'avatar' => $person->avatar,
                'role' => $person->role, 'team' => $person->teamMembership?->team?->name,
                'status' => $person->game_status ?: 'available',
            ]);

        $rooms = MiniGameRoom::query()
            ->where(function ($query) use ($user): void {
                $query->where('status', 'waiting')->orWhereHas('players', fn ($players) => $players->where('user_id', $user->id));
            })
            ->whereNotIn('status', ['cancelled'])
            ->with('players.user:id,name,username,profile_photo_path')
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (MiniGameRoom $room) => $this->roomData($room, $user));

        $invitations = MiniGameInvitation::query()
            ->where('recipient_id', $user->id)->where('status', 'pending')->where('expires_at', '>', now())
            ->with(['sender:id,name,username,profile_photo_path', 'room.players.user:id,name,username,profile_photo_path'])
            ->latest()->get()->map(fn (MiniGameInvitation $invite) => [
                'id' => $invite->id, 'sender' => ['id' => $invite->sender->id, 'name' => $invite->sender->name, 'avatar' => $invite->sender->avatar],
                'room' => $this->roomData($invite->room, $user), 'expires_at' => $invite->expires_at,
            ]);

        $leaderboard = MiniGameStat::query()
            ->selectRaw('user_id, SUM(played) as played, SUM(wins) as wins, SUM(losses) as losses, SUM(draws) as draws, SUM(rating) as rating')
            ->with('user:id,name,username,profile_photo_path')->groupBy('user_id')->orderByDesc('rating')->limit(10)->get()
            ->map(fn (MiniGameStat $stat) => [
                'user' => ['id' => $stat->user_id, 'name' => $stat->user->name, 'avatar' => $stat->user->avatar],
                'played' => (int) $stat->played, 'wins' => (int) $stat->wins, 'losses' => (int) $stat->losses,
                'draws' => (int) $stat->draws, 'rating' => (int) $stat->rating,
            ]);

        return response()->json(['me' => $user->id, 'people' => $people, 'rooms' => $rooms, 'invitations' => $invitations, 'leaderboard' => $leaderboard]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $this->player($request);
        $presence = MiniGamePresence::firstOrCreate(['user_id' => $user->id], ['status' => 'available', 'last_seen_at' => now()]);
        $presence->update(['last_seen_at' => now()]);

        return response()->json(['status' => $presence->status]);
    }

    public function callState(Request $request, MiniGameService $games): JsonResponse
    {
        $user = $this->player($request);
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'on_call'])]]);
        if ($data['status'] === 'on_call') {
            $games->pauseForCall($user);
        } else {
            MiniGamePresence::updateOrCreate(['user_id' => $user->id], ['status' => 'available', 'last_seen_at' => now()]);
        }

        return response()->json(['status' => $data['status']]);
    }

    public function create(Request $request): JsonResponse
    {
        $user = $this->player($request);
        $data = $request->validate(['game_type' => ['required', Rule::in(MiniGameService::GAMES)]]);
        $room = $this->newRoom($user, $data['game_type']);

        return response()->json(['room' => $this->roomData($room->load('players.user'), $user)], 201);
    }

    public function quickMatch(Request $request, MiniGameService $games): JsonResponse
    {
        $user = $this->player($request);
        $data = $request->validate(['game_type' => ['required', Rule::in(MiniGameService::GAMES)]]);
        $room = DB::transaction(function () use ($user, $data): MiniGameRoom {
            $room = MiniGameRoom::query()->where('game_type', $data['game_type'])->where('status', 'waiting')
                ->whereDoesntHave('players', fn ($query) => $query->where('user_id', $user->id))->oldest()->lockForUpdate()->first();
            if (! $room) {
                return $this->newRoom($user, $data['game_type']);
            }
            $this->joinRoom($room, $user);

            return $room;
        });
        if ($room->players()->count() === 2) {
            $room = $games->activate($room);
        }

        return response()->json(['room' => $this->roomData($room->load('players.user'), $user)]);
    }

    public function join(Request $request, MiniGameRoom $room, MiniGameService $games): JsonResponse
    {
        $user = $this->player($request);
        DB::transaction(function () use ($room, $user): void {
            $locked = MiniGameRoom::query()->lockForUpdate()->findOrFail($room->id);
            throw_unless($locked->status === 'waiting' && $locked->players()->count() < 2, ValidationException::withMessages(['room' => 'This room is no longer available.']));
            $this->joinRoom($locked, $user);
        });
        $room = $games->activate($room);

        return response()->json(['room' => $this->roomData($room, $user)]);
    }

    public function invite(Request $request, MiniGameRoom $room): JsonResponse
    {
        $user = $this->player($request);
        throw_unless($room->host_id === $user->id && $room->status === 'waiting', ValidationException::withMessages(['room' => 'Only the host of a waiting room can invite a coworker.']));
        $data = $request->validate(['user_id' => ['required', 'integer', 'different:'.'sender_id']]);
        $recipient = User::query()->whereKey($data['user_id'])->where('status', 'active')->whereIn('role', ['agent', 'team_leader'])->firstOrFail();
        throw_if($recipient->id === $user->id, ValidationException::withMessages(['user_id' => 'Choose another agent.']));
        MiniGameInvitation::updateOrCreate(['room_id' => $room->id, 'recipient_id' => $recipient->id], [
            'sender_id' => $user->id, 'status' => 'pending', 'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json(['message' => 'Challenge sent.']);
    }

    public function invitation(Request $request, MiniGameInvitation $invitation, MiniGameService $games): JsonResponse
    {
        $user = $this->player($request);
        throw_unless($invitation->recipient_id === $user->id && $invitation->status === 'pending' && $invitation->expires_at->isFuture(), ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']));
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])]]);
        if ($data['decision'] === 'decline') {
            $invitation->update(['status' => 'declined']);

            return response()->json(['message' => 'Challenge declined.']);
        }
        DB::transaction(function () use ($invitation, $user): void {
            $room = MiniGameRoom::query()->lockForUpdate()->findOrFail($invitation->room_id);
            throw_unless($room->status === 'waiting' && $room->players()->count() < 2, ValidationException::withMessages(['room' => 'That room was already filled.']));
            $this->joinRoom($room, $user);
            $invitation->update(['status' => 'accepted']);
            MiniGameInvitation::query()->where('room_id', $room->id)->where('id', '!=', $invitation->id)->where('status', 'pending')->update(['status' => 'expired']);
        });
        $room = $games->activate($invitation->room);

        return response()->json(['room' => $this->roomData($room, $user)]);
    }

    public function move(Request $request, MiniGameRoom $room, MiniGameService $games): JsonResponse
    {
        $user = $this->player($request);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', 'array']]);
        $room = $games->move($room, $user, $data['action'], $data['version']);

        return response()->json(['room' => $this->roomData($room, $user)]);
    }

    public function resume(Request $request, MiniGameRoom $room, MiniGameService $games): JsonResponse
    {
        $user = $this->player($request);

        return response()->json(['room' => $this->roomData($games->resume($room, $user), $user)]);
    }

    public function leave(Request $request, MiniGameRoom $room, MiniGameService $games): JsonResponse
    {
        $user = $this->player($request);
        $games->leave($room, $user);

        return response()->json(['message' => 'You left the game.']);
    }

    private function newRoom(User $user, string $game): MiniGameRoom
    {
        return DB::transaction(function () use ($user, $game): MiniGameRoom {
            $room = MiniGameRoom::create(['id' => (string) Str::uuid(), 'game_type' => $game, 'host_id' => $user->id, 'status' => 'waiting']);
            MiniGameRoomPlayer::create(['room_id' => $room->id, 'user_id' => $user->id, 'seat' => 1]);
            MiniGamePresence::updateOrCreate(['user_id' => $user->id], ['status' => 'available', 'current_room_id' => $room->id, 'last_seen_at' => now()]);

            return $room;
        });
    }

    private function joinRoom(MiniGameRoom $room, User $user): void
    {
        throw_if($room->players()->where('user_id', $user->id)->exists(), ValidationException::withMessages(['room' => 'You already joined this room.']));
        MiniGameRoomPlayer::create(['room_id' => $room->id, 'user_id' => $user->id, 'seat' => 2]);
        MiniGamePresence::updateOrCreate(['user_id' => $user->id], ['status' => 'playing', 'current_room_id' => $room->id, 'last_seen_at' => now()]);
    }

    private function roomData(MiniGameRoom $room, User $viewer): array
    {
        $room->loadMissing('players.user');
        $state = $room->state;
        if ($room->game_type === 'poker' && $state) {
            unset($state['deck']);
            if ($room->status !== 'completed') {
                foreach ($state['hands'] as $id => $hand) {
                    if ((int) $id !== $viewer->id) {
                        $state['hands'][$id] = array_fill(0, count($hand), '??');
                    }
                }
            }
        }

        return [
            'id' => $room->id, 'game_type' => $room->game_type, 'status' => $room->status, 'version' => $room->version,
            'host_id' => $room->host_id, 'current_turn_id' => $room->current_turn_id, 'winner_id' => $room->winner_id,
            'state' => $state, 'updated_at' => $room->updated_at,
            'players' => $room->players->sortBy('seat')->values()->map(fn ($player) => [
                'id' => $player->user_id, 'name' => $player->user->name, 'username' => $player->user->username,
                'avatar' => $player->user->avatar, 'seat' => $player->seat, 'symbol' => $player->symbol,
            ]),
        ];
    }

    private function touch(User $user): void
    {
        $presence = MiniGamePresence::firstOrCreate(['user_id' => $user->id], ['status' => 'available', 'last_seen_at' => now()]);
        $presence->update(['last_seen_at' => now()]);
    }

    private function player(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->canAccessAccount() && in_array($user->role, ['agent', 'team_leader'], true), 403);

        return $user;
    }
}
