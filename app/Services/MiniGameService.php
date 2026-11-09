<?php

namespace App\Services;

use App\Models\MiniGamePresence;
use App\Models\MiniGameRoom;
use App\Models\MiniGameStat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MiniGameService
{
    public const GAMES = ['tic_tac_toe', 'connect_four', 'checkers', 'chess', 'poker'];

    public function activate(MiniGameRoom $room): MiniGameRoom
    {
        return DB::transaction(function () use ($room): MiniGameRoom {
            $room = MiniGameRoom::query()->lockForUpdate()->findOrFail($room->id);
            $players = $room->players()->orderBy('seat')->get();
            if ($room->status !== 'waiting' || $players->count() !== 2) {
                return $room->load('players.user');
            }

            $symbols = match ($room->game_type) {
                'tic_tac_toe' => ['X', 'O'], 'connect_four' => ['R', 'Y'],
                'checkers' => ['r', 'b'], 'chess' => ['white', 'black'], default => ['dealer', 'draw'],
            };
            foreach ($players as $index => $player) {
                $player->update(['symbol' => $symbols[$index]]);
            }
            $room->update([
                'status' => 'active',
                'state' => $this->initialState($room->game_type, $players->pluck('user_id')->all()),
                'current_turn_id' => $room->game_type === 'poker' ? null : $players[0]->user_id,
                'started_at' => now(),
                'last_move_at' => now(),
                'version' => $room->version + 1,
            ]);
            MiniGamePresence::query()->whereIn('user_id', $players->pluck('user_id'))->update(['status' => 'playing', 'current_room_id' => $room->id]);

            return $room->fresh('players.user');
        });
    }

    public function move(MiniGameRoom $room, User $user, array $action, int $version): MiniGameRoom
    {
        return DB::transaction(function () use ($room, $user, $action, $version): MiniGameRoom {
            $room = MiniGameRoom::query()->lockForUpdate()->findOrFail($room->id);
            $players = $room->players()->orderBy('seat')->get();
            $player = $players->firstWhere('user_id', $user->id);
            throw_unless($player, ValidationException::withMessages(['room' => 'You are not a player in this room.']));
            throw_if($room->version !== $version, ValidationException::withMessages(['version' => 'The game changed. Refreshing the latest move.']));
            throw_unless($room->status === 'active', ValidationException::withMessages(['room' => 'This game is paused or already finished.']));
            throw_if(MiniGamePresence::query()->where('user_id', $user->id)->where('status', 'on_call')->exists(), ValidationException::withMessages(['room' => 'Gameplay is paused while you are handling a call.']));
            if ($room->game_type !== 'poker') {
                throw_unless($room->current_turn_id === $user->id, ValidationException::withMessages(['move' => 'Wait for your turn.']));
            }

            $state = $room->state;
            $outcome = match ($room->game_type) {
                'tic_tac_toe' => $this->ticTacToe($state, $player->symbol, $action),
                'connect_four' => $this->connectFour($state, $player->symbol, $action),
                'checkers' => $this->checkers($state, $player->symbol, $action),
                'chess' => $this->chess($state, $player->symbol, $action),
                'poker' => $this->poker($state, $user->id, $action, $players->pluck('user_id')->all()),
                default => throw ValidationException::withMessages(['game' => 'Unsupported game.']),
            };

            $updates = ['state' => $outcome['state'], 'last_move_at' => now(), 'version' => $room->version + 1];
            if ($outcome['finished']) {
                $winnerId = $outcome['winner_user_id'] ?? ($outcome['winner_symbol'] ? $players->firstWhere('symbol', $outcome['winner_symbol'])?->user_id : null);
                $updates += ['status' => 'completed', 'winner_id' => $winnerId, 'finished_at' => now(), 'current_turn_id' => null];
            } elseif ($room->game_type !== 'poker') {
                $updates['current_turn_id'] = $players->firstWhere('user_id', '!=', $user->id)?->user_id;
            }
            $room->update($updates);
            if ($outcome['finished']) {
                $this->recordResult($room, $players->pluck('user_id')->all(), $updates['winner_id']);
            }

            return $room->fresh('players.user');
        });
    }

    public function pauseForCall(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $presence = MiniGamePresence::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id], ['last_seen_at' => now()]);
            $presence->update(['status' => 'on_call', 'last_seen_at' => now()]);
            if ($presence->current_room_id) {
                MiniGameRoom::query()->whereKey($presence->current_room_id)->where('status', 'active')->update(['status' => 'paused', 'version' => DB::raw('version + 1')]);
            }
        });
    }

    public function resume(MiniGameRoom $room, User $user): MiniGameRoom
    {
        return DB::transaction(function () use ($room, $user): MiniGameRoom {
            $room = MiniGameRoom::query()->lockForUpdate()->findOrFail($room->id);
            $ids = $room->players()->pluck('user_id');
            throw_unless($ids->contains($user->id), ValidationException::withMessages(['room' => 'You are not a player in this room.']));
            $onCall = MiniGamePresence::query()->whereIn('user_id', $ids)->where('status', 'on_call')->exists();
            throw_if($onCall, ValidationException::withMessages(['room' => 'A player is still handling a call.']));
            if ($room->status === 'paused') {
                $room->update(['status' => 'active', 'version' => $room->version + 1]);
                MiniGamePresence::query()->whereIn('user_id', $ids)->update(['status' => 'playing']);
            }

            return $room->fresh('players.user');
        });
    }

    public function leave(MiniGameRoom $room, User $user): void
    {
        DB::transaction(function () use ($room, $user): void {
            $room = MiniGameRoom::query()->lockForUpdate()->findOrFail($room->id);
            $players = $room->players()->get();
            throw_unless($players->contains('user_id', $user->id), ValidationException::withMessages(['room' => 'You are not in this room.']));
            $winnerId = $players->firstWhere('user_id', '!=', $user->id)?->user_id;
            if (in_array($room->status, ['active', 'paused'], true)) {
                $room->update(['status' => 'completed', 'winner_id' => $winnerId, 'finished_at' => now(), 'current_turn_id' => null, 'version' => $room->version + 1]);
                $this->recordResult($room, $players->pluck('user_id')->all(), $winnerId);
            } elseif ($room->status === 'waiting') {
                $room->update(['status' => 'cancelled', 'finished_at' => now(), 'version' => $room->version + 1]);
            }
            MiniGamePresence::query()->whereIn('user_id', $players->pluck('user_id'))->update(['status' => 'available', 'current_room_id' => null]);
        });
    }

    private function initialState(string $game, array $userIds): array
    {
        if ($game === 'poker') {
            $deck = [];
            foreach (str_split('23456789TJQKA') as $rank) {
                foreach (str_split('SHDC') as $suit) {
                    $deck[] = $rank.$suit;
                }
            }
            shuffle($deck);

            return ['deck' => array_slice($deck, 10), 'hands' => [(string) $userIds[0] => array_slice($deck, 0, 5), (string) $userIds[1] => array_slice($deck, 5, 5)], 'acted' => [], 'labels' => []];
        }
        if ($game === 'checkers') {
            $board = array_fill(0, 64, null);
            for ($row = 0; $row < 3; $row++) {
                for ($col = 0; $col < 8; $col++) {
                    if (($row + $col) % 2 === 1) {
                        $board[$row * 8 + $col] = 'b';
                    }
                }
            }
            for ($row = 5; $row < 8; $row++) {
                for ($col = 0; $col < 8; $col++) {
                    if (($row + $col) % 2 === 1) {
                        $board[$row * 8 + $col] = 'r';
                    }
                }
            }

            return ['board' => $board];
        }
        if ($game === 'chess') {
            $board = array_fill(0, 64, null);
            $back = ['r', 'n', 'b', 'q', 'k', 'b', 'n', 'r'];
            for ($i = 0; $i < 8; $i++) {
                $board[$i] = $back[$i];
                $board[8 + $i] = 'p';
                $board[48 + $i] = 'P';
                $board[56 + $i] = strtoupper($back[$i]);
            }

            return ['board' => $board];
        }

        return ['board' => array_fill(0, $game === 'connect_four' ? 42 : 9, null)];
    }

    private function ticTacToe(array $state, string $symbol, array $action): array
    {
        $index = filter_var($action['index'] ?? null, FILTER_VALIDATE_INT);
        throw_if($index === false || $index < 0 || $index > 8 || $state['board'][$index], ValidationException::withMessages(['move' => 'Choose an empty square.']));
        $state['board'][$index] = $symbol;
        $lines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];
        $won = collect($lines)->contains(fn ($line) => collect($line)->every(fn ($cell) => $state['board'][$cell] === $symbol));

        return ['state' => $state, 'finished' => $won || ! in_array(null, $state['board'], true), 'winner_symbol' => $won ? $symbol : null];
    }

    private function connectFour(array $state, string $symbol, array $action): array
    {
        $column = filter_var($action['column'] ?? null, FILTER_VALIDATE_INT);
        throw_if($column === false || $column < 0 || $column > 6, ValidationException::withMessages(['move' => 'Choose a valid column.']));
        $placed = null;
        for ($row = 5; $row >= 0; $row--) {
            $index = $row * 7 + $column;
            if (! $state['board'][$index]) {
                $state['board'][$index] = $symbol;
                $placed = [$row, $column];
                break;
            }
        }
        throw_if(! $placed, ValidationException::withMessages(['move' => 'That column is full.']));
        [$row, $column] = $placed;
        $won = false;
        foreach ([[0, 1], [1, 0], [1, 1], [1, -1]] as [$dr, $dc]) {
            $count = 1;
            foreach ([1, -1] as $direction) {
                for ($step = 1; $step < 4; $step++) {
                    $r = $row + $dr * $step * $direction;
                    $c = $column + $dc * $step * $direction;
                    if ($r < 0 || $r > 5 || $c < 0 || $c > 6 || $state['board'][$r * 7 + $c] !== $symbol) {
                        break;
                    }
                    $count++;
                }
            }
            $won = $won || $count >= 4;
        }

        return ['state' => $state, 'finished' => $won || ! in_array(null, $state['board'], true), 'winner_symbol' => $won ? $symbol : null];
    }

    private function checkers(array $state, string $symbol, array $action): array
    {
        [$from, $to] = $this->indices($action);
        $piece = $state['board'][$from];
        throw_unless($piece && strtolower($piece) === $symbol, ValidationException::withMessages(['move' => 'Choose one of your pieces.']));
        throw_if($state['board'][$to], ValidationException::withMessages(['move' => 'Choose an empty square.']));
        [$fr, $fc, $tr, $tc] = [intdiv($from, 8), $from % 8, intdiv($to, 8), $to % 8];
        $dr = $tr - $fr;
        $dc = $tc - $fc;
        $king = ctype_upper($piece);
        $forward = $symbol === 'r' ? -1 : 1;
        $simple = abs($dc) === 1 && abs($dr) === 1 && ($king || $dr === $forward);
        $capture = abs($dc) === 2 && abs($dr) === 2 && ($king || $dr === 2 * $forward);
        throw_unless($simple || $capture, ValidationException::withMessages(['move' => 'Use a diagonal move or capture.']));
        if ($capture) {
            $middle = (($fr + $tr) / 2) * 8 + (($fc + $tc) / 2);
            $jumped = $state['board'][(int) $middle];
            throw_unless($jumped && strtolower($jumped) !== $symbol, ValidationException::withMessages(['move' => 'A capture must jump an opponent piece.']));
            $state['board'][(int) $middle] = null;
        }
        $state['board'][$from] = null;
        $state['board'][$to] = ($tr === 0 && $symbol === 'r') || ($tr === 7 && $symbol === 'b') ? strtoupper($piece) : $piece;
        $opponent = $symbol === 'r' ? 'b' : 'r';
        $won = ! collect($state['board'])->contains(fn ($value) => $value && strtolower($value) === $opponent);

        return ['state' => $state, 'finished' => $won, 'winner_symbol' => $won ? $symbol : null];
    }

    private function chess(array $state, string $symbol, array $action): array
    {
        [$from, $to] = $this->indices($action);
        $piece = $state['board'][$from];
        $white = $symbol === 'white';
        throw_unless($piece && ctype_upper($piece) === $white, ValidationException::withMessages(['move' => 'Choose one of your pieces.']));
        $target = $state['board'][$to];
        throw_if($target && ctype_upper($target) === $white, ValidationException::withMessages(['move' => 'That square contains your piece.']));
        [$fr, $fc, $tr, $tc] = [intdiv($from, 8), $from % 8, intdiv($to, 8), $to % 8];
        [$dr, $dc] = [$tr - $fr, $tc - $fc];
        $kind = strtolower($piece);
        $legal = match ($kind) {
            'p' => $dc === 0 && ! $target && $dr === ($white ? -1 : 1) || abs($dc) === 1 && (bool) $target && $dr === ($white ? -1 : 1),
            'n' => in_array([abs($dr), abs($dc)], [[1, 2], [2, 1]], true),
            'b' => abs($dr) === abs($dc) && $this->clearPath($state['board'], $fr, $fc, $tr, $tc),
            'r' => ($dr === 0 || $dc === 0) && $this->clearPath($state['board'], $fr, $fc, $tr, $tc),
            'q' => ($dr === 0 || $dc === 0 || abs($dr) === abs($dc)) && $this->clearPath($state['board'], $fr, $fc, $tr, $tc),
            'k' => max(abs($dr), abs($dc)) === 1,
            default => false,
        };
        throw_unless($legal, ValidationException::withMessages(['move' => 'That piece cannot move there.']));
        $state['board'][$from] = null;
        $state['board'][$to] = $piece;
        $won = $target && strtolower($target) === 'k';

        return ['state' => $state, 'finished' => $won, 'winner_symbol' => $won ? $symbol : null];
    }

    private function poker(array $state, int $userId, array $action, array $playerIds): array
    {
        throw_if(in_array($userId, $state['acted'], true), ValidationException::withMessages(['move' => 'Your draw is already locked in.']));
        $discard = array_values(array_unique(array_map('intval', $action['discard'] ?? [])));
        throw_if(count($discard) > 3 || collect($discard)->contains(fn ($index) => $index < 0 || $index > 4), ValidationException::withMessages(['move' => 'Choose up to three cards to replace.']));
        foreach ($discard as $index) {
            $state['hands'][(string) $userId][$index] = array_shift($state['deck']);
        }
        $state['acted'][] = $userId;
        if (count($state['acted']) < 2) {
            return ['state' => $state, 'finished' => false, 'winner_symbol' => null];
        }
        $scores = [];
        foreach ($playerIds as $id) {
            [$scores[$id], $state['labels'][(string) $id]] = $this->pokerScore($state['hands'][(string) $id]);
        }
        arsort($scores);
        $winner = array_key_first($scores);
        $draw = count(array_unique(array_map('json_encode', $scores))) === 1;

        return ['state' => $state, 'finished' => true, 'winner_symbol' => null, 'winner_user_id' => $draw ? null : $winner];
    }

    private function indices(array $action): array
    {
        $from = filter_var($action['from'] ?? null, FILTER_VALIDATE_INT);
        $to = filter_var($action['to'] ?? null, FILTER_VALIDATE_INT);
        throw_if($from === false || $to === false || $from < 0 || $from > 63 || $to < 0 || $to > 63 || $from === $to, ValidationException::withMessages(['move' => 'Choose a valid start and destination.']));

        return [$from, $to];
    }

    private function clearPath(array $board, int $fr, int $fc, int $tr, int $tc): bool
    {
        $dr = $tr <=> $fr;
        $dc = $tc <=> $fc;
        for ($r = $fr + $dr, $c = $fc + $dc; $r !== $tr || $c !== $tc; $r += $dr, $c += $dc) {
            if ($board[$r * 8 + $c]) {
                return false;
            }
        }

        return true;
    }

    private function pokerScore(array $hand): array
    {
        $values = array_map(fn ($card) => strpos('23456789TJQKA', $card[0]) + 2, $hand);
        rsort($values);
        $counts = array_count_values($values);
        rsort($counts);
        $flush = count(array_unique(array_map(fn ($card) => $card[1], $hand))) === 1;
        $unique = array_values(array_unique($values));
        sort($unique);
        $straight = count($unique) === 5 && max($unique) - min($unique) === 4;
        [$category, $label] = match (true) {
            $straight && $flush => [8, 'Straight flush'], $counts === [4, 1] => [7, 'Four of a kind'],
            $counts === [3, 2] => [6, 'Full house'], $flush => [5, 'Flush'], $straight => [4, 'Straight'],
            $counts === [3, 1, 1] => [3, 'Three of a kind'], $counts === [2, 2, 1] => [2, 'Two pair'],
            $counts === [2, 1, 1, 1] => [1, 'One pair'], default => [0, 'High card'],
        };

        return [[$category, ...$values], $label];
    }

    private function recordResult(MiniGameRoom $room, array $playerIds, ?int $winnerId): void
    {
        foreach ($playerIds as $id) {
            $stat = MiniGameStat::firstOrCreate(['user_id' => $id, 'game_type' => $room->game_type]);
            $result = $winnerId === null ? 'draws' : ($winnerId === $id ? 'wins' : 'losses');
            $stat->increment('played');
            $stat->increment($result);
            $stat->increment('rating', $winnerId === null ? 2 : ($winnerId === $id ? 15 : -10));
        }
        MiniGamePresence::query()->whereIn('user_id', $playerIds)->update(['status' => 'available', 'current_room_id' => null]);
    }
}
