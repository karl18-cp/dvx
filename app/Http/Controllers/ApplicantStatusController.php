<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicantStatusController extends Controller
{
    public function challenge(Request $request): JsonResponse
    {
        $first = random_int(2, 9);
        $second = random_int(1, 9);
        $request->session()->put('applicant_status_challenge', [
            'answer' => $first + $second,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return response()->json(['question' => "What is {$first} + {$second}?"])->header('Cache-Control', 'private, no-store');
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'challenge_answer' => ['required', 'integer'],
        ]);
        $challenge = $request->session()->pull('applicant_status_challenge');
        if (! $challenge || $challenge['expires_at'] < now()->timestamp || (int) $challenge['answer'] !== (int) $data['challenge_answer']) {
            return response()->json(['message' => 'Human verification failed. Please try a new question.'], 422);
        }

        $application = JobApplication::query()
            ->where('phone_normalized', $this->normalizePhone($data['phone']))
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])
            ->latest()
            ->first();
        if (! $application) {
            return response()->json(['message' => 'No application matched those details.'], 404);
        }

        return response()->json([
            'application' => [
                'applicant_name' => $application->first_name.' '.$application->last_name,
                'position' => $application->position,
                'applicant_stage' => $application->applicant_stage,
                'applicant_update' => $application->applicant_update,
                'scheduled_start_at' => $application->scheduled_start_at?->toIso8601String(),
                'schedule_label' => $application->scheduleLabel(),
                'applied_at' => $application->created_at->toDateString(),
                'updated_at' => $application->reviewed_at?->toIso8601String() ?? $application->updated_at->toIso8601String(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
