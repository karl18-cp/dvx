<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ChatMessage;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiverTextTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'agent'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function direct(User $first, User $second): int
    {
        return $this->actingAs($first)->postJson('/divertext/direct', ['user_id' => $second->id])->assertOk()->json('id');
    }

    private function message(int $room, string $body = 'Hello team'): int
    {
        return $this->postJson("/divertext/$room/messages", ['request_id' => Str::uuid(), 'body' => $body])->assertOk()->json('id');
    }

    public function test_direct_conversations_are_unique_and_private_even_from_admins(): void
    {
        $first = $this->person();
        $second = $this->person();
        $admin = $this->person('admin');
        $room = $this->direct($first, $second);
        $id = $this->message($room, 'Private hello');
        $this->assertSame($room, $this->direct($second, $first));
        $this->getJson("/divertext/$room/messages")->assertJsonPath('messages.0.body', 'Private hello');
        $this->actingAs($admin)->getJson("/divertext/$room/messages")->assertForbidden();
        $this->postJson("/divertext/$room/messages", ['request_id' => Str::uuid(), 'body' => 'No'])->assertForbidden();
        $this->patchJson("/divertext/$room/read", ['message_id' => $id])->assertForbidden();
        $this->getJson('/divertext/rooms')->assertExactJson([]);
        $this->assertDatabaseCount('chat_conversations', 1);
    }

    public function test_team_chats_follow_membership_and_leadership_with_admin_access(): void
    {
        $campaign = Campaign::create(['name' => 'Chat campaign', 'abbreviation' => 'CHAT']);
        $team = Team::create(['name' => 'Support', 'campaign_id' => $campaign->id]);
        $member = $this->person();
        $leader = $this->person('team_leader');
        $admin = $this->person('admin');
        $other = $this->person();
        TeamMember::create(['team_id' => $team->id, 'user_id' => $member->id]);
        TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        $room = $this->actingAs($member)->getJson('/divertext/rooms')->assertOk()->json('0.id');
        $this->message($room);
        foreach ([$leader, $admin] as $user) {
            $this->actingAs($user)->getJson("/divertext/$room/messages")->assertOk();
        }
        $this->actingAs($other)->getJson("/divertext/$room/messages")->assertForbidden();
        TeamMember::where('user_id', $member->id)->delete();
        $this->actingAs($member)->getJson("/divertext/$room/messages")->assertForbidden();
        $this->getJson('/divertext/rooms')->assertExactJson([]);
        $this->getJson('/divertext/unread')->assertJsonPath('count', 0);
    }

    public function test_unread_is_per_recipient_and_read_cursor_never_moves_backwards(): void
    {
        $first = $this->person();
        $second = $this->person();
        $room = $this->direct($first, $second);
        $one = $this->message($room, 'First');
        $two = $this->message($room, 'Second');
        $this->getJson('/divertext/unread')->assertJsonPath('count', 0);
        $this->actingAs($second)->getJson('/divertext/unread')->assertJsonPath('count', 2);
        $this->getJson('/divertext/rooms')->assertJsonPath('0.unread_count', 2);
        $this->patchJson("/divertext/$room/read", ['message_id' => $two])->assertOk();
        $this->patchJson("/divertext/$room/read", ['message_id' => $one])->assertOk();
        $this->getJson('/divertext/unread')->assertJsonPath('count', 0);
        $this->actingAs($first);
        $this->message($room, 'Third');
        $this->actingAs($second)->getJson('/divertext/unread')->assertJsonPath('count', 1);
    }

    public function test_send_retries_are_idempotent_and_sender_cannot_be_forged(): void
    {
        $first = $this->person();
        $second = $this->person();
        $room = $this->direct($first, $second);
        $data = ['request_id' => Str::uuid(), 'body' => 'Once', 'sender_id' => $second->id];
        $this->postJson("/divertext/$room/messages", $data)->assertOk()->assertJsonPath('sender_id', $first->id);
        $this->postJson("/divertext/$room/messages", $data)->assertOk();
        $this->assertDatabaseCount('chat_messages', 1);
        $this->actingAs($second)->postJson("/divertext/$room/messages", $data)->assertForbidden();
    }

    public function test_history_pagination_preserves_order_without_skipping_messages(): void
    {
        $first = $this->person();
        $room = $this->direct($first, $this->person());
        for ($i = 1; $i <= 55; $i++) {
            ChatMessage::create(['request_id' => Str::uuid(), 'conversation_id' => $room, 'sender_id' => $first->id, 'sender_name' => $first->name, 'body' => "Message $i"]);
        }
        $data = $this->getJson("/divertext/$room/messages")->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true)->assertJsonPath('messages.0.body', 'Message 6')->json();
        $this->getJson("/divertext/$room/messages?before=".$data['messages'][0]['id'])->assertJsonCount(5, 'messages')->assertJsonPath('has_more', false)->assertJsonPath('messages.0.body', 'Message 1');
        $this->getJson("/divertext/$room/messages?after=".$data['messages'][48]['id'])->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Message 55');
    }

    public function test_validation_inactive_accounts_and_foreign_read_cursor(): void
    {
        $this->getJson('/divertext/rooms')->assertUnauthorized();
        $first = $this->person();
        $peer = $this->person();
        $room = $this->direct($first, $peer);
        $this->postJson('/divertext/direct', ['user_id' => $first->id])->assertUnprocessable();
        $this->postJson("/divertext/$room/messages", ['request_id' => Str::uuid(), 'body' => ' '])->assertUnprocessable();
        $this->postJson("/divertext/$room/messages", ['request_id' => Str::uuid(), 'body' => str_repeat('a', 4001)])->assertUnprocessable();
        $foreign = $this->direct($first, $this->person());
        $foreignMessage = $this->message($foreign);
        $this->patchJson("/divertext/$room/read", ['message_id' => $foreignMessage])->assertUnprocessable();
        $peer->update(['status' => 'inactive']);
        $this->postJson("/divertext/$room/messages", ['request_id' => Str::uuid(), 'body' => 'Hello'])->assertUnprocessable();
        $this->actingAs($peer)->getJson('/divertext/rooms')->assertForbidden();
    }
}
