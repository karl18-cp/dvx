<?php

namespace Tests\Feature;

use App\Models\ApplicantExamAttempt;
use App\Models\ApplicantExamQuestion;
use App\Models\ApplicantExamSetting;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicantExamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function question(array $extra = []): array
    {
        return [...['category' => 'Numerical reasoning', 'prompt' => 'An agent handles 6 calls per hour. How many calls in 3 hours?', 'options' => ['9', '12', '18', '24'], 'correct_index' => 2, 'explanation' => '6 multiplied by 3 equals 18.', 'approved' => true], ...$extra];
    }

    private function enable(): void
    {
        ApplicantExamSetting::create(['id' => 1, 'enabled' => true, 'question_count' => 1]);
        ApplicantExamQuestion::create($this->question());
    }

    private function payload(array $extra = []): array
    {
        return [...['first_name' => 'Jamie', 'last_name' => 'Santos', 'email' => 'jamie@example.test', 'phone' => '09171234567', 'position' => 'Customer Service Representative', 'years_experience' => 2], ...$extra];
    }

    private function start(): ApplicantExamAttempt
    {
        $this->withSession(['exam_test' => true]);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $response = $this->postJson('/application-exam/start')->assertOk();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->assertArrayNotHasKey('correct_option', $response->json('questions.0'));
        $this->assertArrayNotHasKey('explanation', $response->json('questions.0'));

        return ApplicantExamAttempt::findOrFail($response->json('id'));
    }

    public function test_admin_and_qa_only_can_manage_questions_settings_and_results(): void
    {
        $this->get('/management/applicant-exams')->assertRedirect('/login');
        foreach (['admin', 'qa_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));
            $this->get('/management/applicant-exams')->assertOk();
            $this->post('/management/applicant-exams/questions', $this->question())->assertSessionHasNoErrors();
        }
        foreach (['agent', 'team_leader', 'manager', 'trainee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));
            $response = $this->postJson('/management/applicant-exams/questions', $this->question());
            $this->assertContains($response->status(), [403]);
        }
        $this->assertSame(2, ApplicantExamQuestion::count());
    }

    public function test_enabled_exam_cannot_be_bypassed_and_grade_uses_snapshot(): void
    {
        $this->enable();
        $this->post('/apply', $this->payload())->assertSessionHasErrors('exam_attempt_id');
        $attempt = $this->start();
        $q = $attempt->questions[0];
        ApplicantExamQuestion::first()->update(['correct_index' => 0, 'prompt' => 'Edited after start']);
        $this->post('/apply', $this->payload(['exam_attempt_id' => $attempt->id, 'exam_answers' => [$q['id'] => $q['correct_option']], 'score' => 0]))->assertSessionHasNoErrors();
        $attempt->refresh();
        $this->assertSame(1, $attempt->score);
        $this->assertTrue($attempt->passed);
        $this->assertNotNull($attempt->job_application_id);
        $this->assertStringContainsString('6 calls', $attempt->questions[0]['prompt']);
        $this->post('/apply', $this->payload(['exam_attempt_id' => $attempt->id, 'exam_answers' => [$q['id'] => $q['correct_option']]]))->assertSessionHasErrors('exam');
        $this->assertSame(1, JobApplication::count());
    }

    public function test_wrong_answers_are_saved_for_human_review_and_private_result_is_authorized(): void
    {
        $this->enable();
        $attempt = $this->start();
        $q = $attempt->questions[0];
        $wrong = collect($q['options'])->first(fn ($o) => $o['id'] !== $q['correct_option'])['id'];
        $this->post('/apply', $this->payload(['exam_attempt_id' => $attempt->id, 'exam_answers' => [$q['id'] => $wrong]]))->assertSessionHasNoErrors();
        $this->assertSame(0, $attempt->fresh()->score);
        $this->assertFalse($attempt->fresh()->passed);
        $url = '/management/applicant-exams/results/'.$attempt->id;
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'qa_admin', 'status' => 'active']))->getJson($url)->assertOk()->assertJsonPath('questions.0.correct_option', $q['correct_option']);
        $this->actingAs(User::factory()->create(['role' => 'team_leader', 'status' => 'active']))->getJson($url)->assertForbidden();
    }

    public function test_expired_cross_session_and_invalid_answers_cannot_submit(): void
    {
        $this->enable();
        $attempt = $this->start();
        $q = $attempt->questions[0];
        $payload = $this->payload(['exam_attempt_id' => $attempt->id, 'exam_answers' => [$q['id'] => (string) Str::uuid()]]);
        $this->post('/apply', $payload)->assertSessionHasErrors('exam');
        $payload['exam_answers'] = [$q['id'] => $q['correct_option']];
        $attempt->update(['session_hash' => str_repeat('0', 64)]);
        $this->post('/apply', $payload)->assertSessionHasErrors('exam');
        $attempt->update(['session_hash' => hash('sha256', session()->getId()), 'expires_at' => now()->subMinute()]);
        $this->post('/apply', $payload)->assertSessionHasErrors('exam');
        $this->assertSame(0, JobApplication::count());
    }

    public function test_start_reuses_unexpired_attempt_and_drafts_are_never_selected(): void
    {
        $this->enable();
        ApplicantExamQuestion::create($this->question(['approved' => false, 'prompt' => 'Draft private question']));
        $attempt = $this->start();
        $this->postJson('/application-exam/start')->assertOk()->assertJsonPath('id', $attempt->id);
        $this->assertNotSame('Draft private question', $attempt->questions[0]['prompt']);
    }

    public function test_cannot_enable_without_enough_approved_questions_or_remove_required_question(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $settings = ['title' => 'Exam', 'enabled' => true, 'question_count' => 2, 'duration_minutes' => 15, 'passing_percent' => 70];
        $this->put('/management/applicant-exams/settings', $settings)->assertSessionHasErrors('enabled');
        $q = ApplicantExamQuestion::create($this->question());
        $settings['question_count'] = 1;
        $this->put('/management/applicant-exams/settings', $settings)->assertSessionHasNoErrors();
        $this->put('/management/applicant-exams/questions/'.$q->id, $this->question(['approved' => false]))->assertSessionHasErrors('approved');
    }

    public function test_ai_questions_are_drafts_until_reviewed(): void
    {
        config(['services.openai.key' => 'test-key']);
        $draft = collect($this->question())->except('approved')->all();
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['questions' => [$draft]])]]]]])]);
        $this->actingAs(User::factory()->create(['role' => 'qa_admin', 'status' => 'active']))
            ->post('/management/applicant-exams/generate', ['count' => 1, 'difficulty' => 'medium', 'focus' => 'Call-center numeracy'])->assertSessionHasNoErrors();
        $this->assertFalse(ApplicantExamQuestion::firstOrFail()->approved);
        Http::assertSent(fn ($request) => $request['store'] === false && $request['text']['format']['strict'] === true && ! str_contains($request['input'], 'email'));
    }

    public function test_missing_key_and_malformed_ai_response_save_no_questions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $input = ['count' => 1, 'difficulty' => 'medium', 'focus' => 'Logic'];
        config(['services.openai.key' => null]);
        $this->post('/management/applicant-exams/generate', $input)->assertSessionHasErrors('generation');
        Http::assertNothingSent();
        config(['services.openai.key' => 'test-key']);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => 'not json']]]]])]);
        $this->post('/management/applicant-exams/generate', $input)->assertSessionHasErrors('generation');
        $this->assertSame(0,ApplicantExamQuestion::count());
    }
}
