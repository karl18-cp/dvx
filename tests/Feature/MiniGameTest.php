<?php

namespace Tests\Feature;

use App\Models\MiniGameRoom;
use App\Models\MiniGameStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiniGameTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $role = 'agent'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    public function test_only_active_agents_and_team_leaders_can_access_games(): void
    {
        $this->withoutVite();
        $this->get('/mini-games')->assertRedirect('/login');
        $this->actingAs($this->agent())->get('/mini-games')->assertOk();
        $this->actingAs($this->agent('team_leader'))->get('/mini-games/snapshot')->assertOk();
        $this->actingAs($this->agent('admin'))->get('/mini-games/snapshot')->assertForbidden();
    }

    public function test_quick_match_activates_room_and_tic_tac_toe_records_result(): void
    {
        $one = $this->agent();
        $two = $this->agent();
        $waiting = $this->actingAs($one)->postJson('/mini-games/quick-match', ['game_type' => 'tic_tac_toe'])
            ->assertOk()->assertJsonPath('room.status', 'waiting')->json('room');
        $active = $this->actingAs($two)->postJson('/mini-games/quick-match', ['game_type' => 'tic_tac_toe'])
            ->assertOk()->assertJsonPath('room.id', $waiting['id'])->assertJsonPath('room.status', 'active')->json('room');

        $moves = [[$one, 0], [$two, 3], [$one, 1], [$two, 4], [$one, 2]];
        foreach ($moves as [$player, $index]) {
            $active = $this->actingAs($player)->postJson("/mini-games/rooms/{$active['id']}/move", [
                'version' => $active['version'], 'action' => ['index' => $index],
            ])->assertOk()->json('room');
        }

        $this->assertSame('completed', $active['status']);
        $this->assertSame($one->id, $active['winner_id']);
        $this->assertDatabaseHas('mini_game_stats', ['user_id' => $one->id, 'wins' => 1, 'played' => 1]);
        $this->assertDatabaseHas('mini_game_stats', ['user_id' => $two->id, 'losses' => 1, 'played' => 1]);
    }

    public function test_incoming_call_pauses_game_until_both_players_are_available(): void
    {
        $one = $this->agent();
        $two = $this->agent();
        $room = $this->actingAs($one)->postJson('/mini-games/quick-match', ['game_type' => 'connect_four'])->json('room');
        $room = $this->actingAs($two)->postJson('/mini-games/quick-match', ['game_type' => 'connect_four'])->json('room');

        $this->actingAs($one)->patchJson('/mini-games/call-state', ['status' => 'on_call'])->assertOk();
        $this->assertSame('paused', MiniGameRoom::findOrFail($room['id'])->status);
        $this->actingAs($two)->postJson("/mini-games/rooms/{$room['id']}/resume")->assertUnprocessable();
        $this->actingAs($one)->patchJson('/mini-games/call-state', ['status' => 'available'])->assertOk();
        $this->actingAs($two)->postJson("/mini-games/rooms/{$room['id']}/resume")->assertOk()->assertJsonPath('room.status', 'active');
    }

    public function test_invites_and_poker_hands_are_private_until_completion(): void
    {
        $one = $this->agent();
        $two = $this->agent();
        $room = $this->actingAs($one)->postJson('/mini-games/rooms', ['game_type' => 'poker'])->json('room');
        $this->actingAs($one)->postJson("/mini-games/rooms/{$room['id']}/invite", ['user_id' => $two->id])->assertOk();
        $inviteId = $this->actingAs($two)->getJson('/mini-games/snapshot')->assertOk()->assertJsonCount(1, 'invitations')->json('invitations.0.id');
        $active = $this->actingAs($two)->patchJson("/mini-games/invitations/{$inviteId}", ['decision' => 'accept'])->assertOk()->json('room');

        $snapshot = $this->actingAs($one)->getJson('/mini-games/snapshot')->assertOk()->json();
        $visible = collect($snapshot['rooms'])->firstWhere('id', $active['id']);
        $this->assertNotSame(['??', '??', '??', '??', '??'], $visible['state']['hands'][(string) $one->id]);
        $this->assertSame(['??', '??', '??', '??', '??'], $visible['state']['hands'][(string) $two->id]);
        $this->assertSame(0, MiniGameStat::count());
    }
}
