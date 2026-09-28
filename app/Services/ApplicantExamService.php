<?php

namespace App\Services;

use App\Models\ApplicantExamAttempt;
use App\Models\ApplicantExamQuestion;
use App\Models\ApplicantExamSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicantExamService
{
    public function settings(): ApplicantExamSetting
    {
        return ApplicantExamSetting::firstOrCreate(['id' => 1])->fresh();
    }

    public function publicSettings(): array
    {
        $settings = $this->settings();

        return $settings->only(['title', 'instructions', 'enabled', 'question_count', 'duration_minutes', 'passing_percent']);
    }

    public function start(Request $request): array
    {
        $settings = $this->settings();
        abort_unless($settings->enabled, 404);
        $sessionHash = hash('sha256', $request->session()->getId());
        $attempt = ApplicantExamAttempt::where('session_hash', $sessionHash)->whereNull('submitted_at')->where('expires_at', '>', now())->latest()->first();
        if (! $attempt) {
            $bank = ApplicantExamQuestion::where('approved', true)->inRandomOrder()->limit($settings->question_count)->get();
            if ($bank->count() !== $settings->question_count) {
                throw ValidationException::withMessages(['exam' => 'The exam is temporarily unavailable. Please try again later.']);
            }
            $questions = $bank->map(function ($q): array {
                $options = collect($q->options)->map(fn ($label, $index) => ['id' => (string) Str::uuid(), 'label' => $label, 'correct' => $index === $q->correct_index])->shuffle()->values();

                return ['id' => (string) Str::uuid(), 'bank_id' => $q->id, 'category' => $q->category, 'prompt' => $q->prompt, 'options' => $options->map(fn ($o) => ['id' => $o['id'], 'label' => $o['label']])->all(), 'correct_option' => $options->firstWhere('correct', true)['id'], 'explanation' => $q->explanation];
            })->all();
            $attempt = ApplicantExamAttempt::create(['id' => (string) Str::uuid(), 'session_hash' => $sessionHash, 'title' => $settings->title, 'questions' => $questions, 'passing_percent' => $settings->passing_percent, 'total' => count($questions), 'expires_at' => now()->addMinutes($settings->duration_minutes)]);
        }

        // Never serialize answer keys or explanations to the applicant.
        return ['id' => $attempt->id, 'title' => $attempt->title, 'expires_at' => $attempt->expires_at->toISOString(), 'server_now' => now()->toISOString(), 'questions' => array_map(fn ($q) => collect($q)->only(['id', 'category', 'prompt', 'options'])->all(), $attempt->questions)];
    }

    /** Must be called inside the application submission transaction. */
    public function grade(Request $request): ?ApplicantExamAttempt
    {
        if (! $request->filled('exam_attempt_id') && ! $this->settings()->enabled) {
            return null;
        }
        $data = $request->validate(['exam_attempt_id' => ['required', 'uuid'], 'exam_answers' => ['required', 'array', 'max:50'], 'exam_answers.*' => ['required', 'uuid']]);
        $attempt = ApplicantExamAttempt::whereKey($data['exam_attempt_id'])->lockForUpdate()->first();
        if (! $attempt || ! hash_equals($attempt->session_hash, hash('sha256', $request->session()->getId())) || $attempt->submitted_at) {
            throw ValidationException::withMessages(['exam' => 'Start your own exam before submitting this application.']);
        }
        if ($attempt->expires_at->isPast()) {
            throw ValidationException::withMessages(['exam' => 'Your exam has expired. Start a new exam; your application details are still here.']);
        }
        $answers = $data['exam_answers'];
        if (count($answers) !== $attempt->total) {
            throw ValidationException::withMessages(['exam' => 'Answer every question before submitting.']);
        }
        $score = 0;
        foreach ($attempt->questions as $q) {
            $answer = $answers[$q['id']] ?? null;
            if (! in_array($answer, array_column($q['options'], 'id'), true)) {
                throw ValidationException::withMessages(['exam' => 'Choose a valid answer for every question.']);
            }
            if ($answer === $q['correct_option']) {
                $score++;
            }
        }
        $passed = $score * 100 >= $attempt->passing_percent * $attempt->total;
        $attempt->fill(['answers' => $answers, 'score' => $score, 'passed' => $passed, 'submitted_at' => now()]);

        return $attempt;
    }
}
