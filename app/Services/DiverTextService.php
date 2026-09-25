<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DiverTextService
{
    public function teams(User $user): Builder
    {
        if ($user->role === 'team_leader') {
            return app(TeamLeaderWorkspaceService::class)->teams($user);
        }
        return Team::query()->when($user->role !== 'admin', fn ($q) => $q->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('user_id', $user->id))->orWhereHas('leaderAssignment', fn ($m) => $m->where('user_id', $user->id))));
    }

    public function people(User $user): Builder
    {
        return $user->role === 'team_leader' ? app(TeamLeaderWorkspaceService::class)->members($user) : User::query();
    }

    public function visible(User $user): Builder
    {
        return ChatConversation::where(function ($q) use ($user) {
            $q->where(fn ($q) => $q->where('type', 'direct')->where(fn ($q) => $q->where('first_user_id', $user->id)->orWhere('second_user_id', $user->id))
                ->when($user->role === 'team_leader', fn ($q) => $q->where(fn ($q) => $q->whereIn('first_user_id', $this->people($user)->select('users.id'))->orWhereIn('second_user_id', $this->people($user)->select('users.id')))))
                ->orWhere(fn ($q) => $q->where('type', 'team')->whereIn('team_id', $this->teams($user)->select('teams.id')));
        });
    }

    public function unread(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q->where('sender_id', '!=', $user->id)->orWhereNull('sender_id'))
            ->whereRaw('chat_messages.id > COALESCE((SELECT last_read_message_id FROM chat_reads WHERE chat_reads.conversation_id = chat_messages.conversation_id AND chat_reads.user_id = ?), 0)', [$user->id]);
    }

    public function rooms(User $user)
    {
        foreach ($this->teams($user)->get(['id']) as $team) {
            ChatConversation::firstOrCreate(['conversation_key' => 'team:'.$team->id], ['type' => 'team', 'team_id' => $team->id]);
        }

        return $this->visible($user)->with(['firstUser', 'secondUser', 'team.campaign', 'latestMessage'])->withCount(['messages as unread_count' => fn ($q) => $this->unread($q, $user)])->get()
            ->sortByDesc(fn ($room) => $room->latestMessage?->id ?? 0)->values()->map(fn ($room) => $this->room($room, $user));
    }

    public function room(ChatConversation $room, User $user): array
    {
        $peer = $room->first_user_id === $user->id ? $room->secondUser : $room->firstUser;

        return ['id' => $room->id, 'type' => $room->type, 'name' => $room->type === 'team' ? $room->team->name : ($peer?->name ?? 'Former employee'), 'subtitle' => $room->type === 'team' ? ($room->team->campaign?->name ?? 'Team chat') : ($peer?->username ?? 'Direct message'), 'avatar' => $room->type === 'direct' ? ($peer?->avatar ?? '') : '', 'can_send' => $room->type === 'team' || $peer?->status === 'active', 'unread_count' => (int) ($room->unread_count ?? 0), 'latest_message' => $room->latestMessage ? ['body' => mb_substr($room->latestMessage->body, 0, 100), 'sender_name' => $room->latestMessage->sender_name, 'created_at' => $room->latestMessage->created_at] : null];
    }
}
