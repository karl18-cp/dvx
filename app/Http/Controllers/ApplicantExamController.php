<?php

namespace App\Http\Controllers;

use App\Models\ApplicantExamAttempt;
use App\Models\ApplicantExamQuestion;
use App\Services\ApplicantExamGenerator;
use App\Services\ApplicantExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ApplicantExamController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->canAccessAccount() && in_array($request->user()->role, ['admin', 'qa_admin'], true), 403);
    }

    public function index(Request $request, ApplicantExamService $exams)
    {
        $this->authorizeManager($request);

        return Inertia::render('applicant-exams', [
            'settings' => $exams->publicSettings(), 'aiConfigured' => (bool) config('services.openai.key'),
            'questions' => ApplicantExamQuestion::latest()->paginate(15, ['*'], 'questions_page')->withQueryString(),
            'approvedCount' => ApplicantExamQuestion::where('approved', true)->count(),
            'results' => ApplicantExamAttempt::whereNotNull('job_application_id')->with('application:id,first_name,last_name,email,position')
                ->latest('submitted_at')->paginate(15, ['id', 'job_application_id', 'title', 'score', 'total', 'passed', 'submitted_at', 'passing_percent'], 'results_page')->withQueryString(),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function settings(Request $request, ApplicantExamService $exams)
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'instructions' => ['nullable', 'string', 'max:3000'],
            'enabled' => ['required', 'boolean'], 'question_count' => ['required', 'integer', 'between:1,30'],
            'duration_minutes' => ['required', 'integer', 'between:5,120'], 'passing_percent' => ['required', 'integer', 'between:1,100'],
        ]);
        DB::transaction(function () use ($exams, $data) {
            $settings = $exams->settings()->newQuery()->lockForUpdate()->findOrFail(1);
            if ($data['enabled'] && ApplicantExamQuestion::where('approved', true)->count() < $data['question_count']) {
                throw ValidationException::withMessages(['enabled' => 'Approve enough questions before enabling this exam.']);
            }
            $settings->update($data);
        });

        return back()->with('status', 'Exam settings saved. Scores support human review; applications are not automatically rejected.');
    }

    public function question(Request $request, ApplicantExamService $exams, ?ApplicantExamQuestion $question = null)
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'], 'prompt' => ['required', 'string', 'max:3000'],
            'options' => ['required', 'array', 'list', 'size:4'], 'options.*' => ['required', 'string', 'max:1000', 'distinct:ignore_case'],
            'correct_index' => ['required', 'integer', 'between:0,3'], 'explanation' => ['required', 'string', 'max:3000'],
            'approved' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $exams, $question, $data) {
            $settings = $exams->settings()->newQuery()->lockForUpdate()->findOrFail(1);
            if ($question?->exists && ! $data['approved'] && $settings->enabled && ApplicantExamQuestion::where('approved', true)->where('id', '!=', $question->id)->count() < $settings->question_count) {
                throw ValidationException::withMessages(['approved' => 'Disable the exam first or approve more questions before removing this question from the pool.']);
            }
            if ($question?->exists) {
                $question->update($data);
            } else {
                ApplicantExamQuestion::create([...$data, 'created_by' => $request->user()->id]);
            }
        });

        return back()->with('status', 'Question saved. Existing attempts keep their original questions and answer keys.');
    }

    public function generate(Request $request, ApplicantExamGenerator $generator)
    {
        $this->authorizeManager($request);
        $input = $request->validate(['count' => ['required', 'integer', 'between:1,5'], 'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])], 'focus' => ['required', 'string', 'max:500']]);
        $questions = $generator->generate($input);
        DB::transaction(function () use ($questions, $request) {
            foreach ($questions as $question) {
                ApplicantExamQuestion::create([...$question, 'approved' => false, 'created_by' => $request->user()->id]);
            }
        });

        return back()->with('status', count($questions).' AI drafts created. Review the wording, answer key and explanation, then approve each question.');
    }

    public function result(Request $request, ApplicantExamAttempt $attempt)
    {
        $this->authorizeManager($request);
        abort_unless($attempt->job_application_id, 404);

        return response()->json($attempt->load('application:id,first_name,last_name,email,position')->only(['id', 'title', 'questions', 'answers', 'score', 'total', 'passed', 'passing_percent', 'submitted_at', 'application']))->header('Cache-Control', 'private, no-store');
    }

    public function start(Request $request, ApplicantExamService $exams)
    {
        return response()->json($exams->start($request))->header('Cache-Control', 'private, no-store');
    }
}
