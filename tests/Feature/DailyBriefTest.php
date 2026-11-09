<?php

namespace Tests\Feature;

use App\Models\DailyLearningCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyBriefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake([
            'news.google.com/*' => Http::response(<<<'XML'
                <?xml version="1.0"?><rss><channel><item><title>Sample headline</title><link>https://example.com/story</link><pubDate>Mon, 19 Oct 2026 00:00:00 GMT</pubDate><source>Example News</source></item></channel></rss>
                XML, 200, ['Content-Type' => 'application/rss+xml']),
        ]);
    }

    public function test_public_daily_brief_contains_news_word_and_lesson(): void
    {
        $this->getJson('/daily-brief')->assertOk()
            ->assertJsonPath('news.us.items.0.title', 'Sample headline')
            ->assertJsonStructure(['date', 'word' => ['term', 'meaning', 'example'], 'lesson' => ['question', 'options', 'explanation'], 'progress']);
    }

    public function test_authenticated_user_can_complete_one_daily_lesson_and_build_a_streak(): void
    {
        $user = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $brief = $this->actingAs($user)->getJson('/daily-brief')->assertOk()->json();

        $this->postJson('/daily-brief/complete', ['answer' => $brief['lesson']['correct_index']])
            ->assertOk()->assertJsonPath('correct', true)->assertJsonPath('streak', 1);

        $this->assertDatabaseCount('daily_learning_completions', 1);
        $this->assertTrue(DailyLearningCompletion::firstOrFail()->is_correct);

        $this->postJson('/daily-brief/complete', ['answer' => $brief['lesson']['correct_index']])->assertOk();
        $this->assertDatabaseCount('daily_learning_completions', 1);
    }

    public function test_guest_cannot_write_learning_progress(): void
    {
        $this->postJson('/daily-brief/complete', ['answer' => 0])->assertUnauthorized();
    }
}
