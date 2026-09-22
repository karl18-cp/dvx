<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobApplicationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'], 'phone' => ['required', 'string', 'max:40'],
            'position' => ['required', Rule::in(['Customer Service Representative', 'Other'])],
            'location' => ['nullable', 'string', 'max:150'], 'years_experience' => ['required', 'integer', 'min:0', 'max:50'],
            'message' => ['nullable', 'string', 'max:3000'], 'resume' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);
        unset($data['resume']);
        $digits = preg_replace('/\D+/', '', $data['phone']) ?: '';
        $data['phone_normalized'] = strlen($digits) > 10 ? substr($digits, -10) : $digits;
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $path = $file->store('job-applications/'.now()->format('Y/m'), 'local');
            $data = [...$data, 'resume_disk' => 'local', 'resume_path' => $path, 'resume_original_name' => $file->getClientOriginalName(), 'resume_mime' => $file->getMimeType(), 'resume_size' => $file->getSize()];
        }
        JobApplication::query()->create($data);

        return back()->with('status', 'Application submitted successfully. Our recruitment team will review your information.');
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'stage', 'position']);
        $query = JobApplication::query()->with('reviewer:id,name');
        $query->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($nested) => $nested->where('first_name', 'like', "%{$value}%")->orWhere('last_name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")))
            ->when($filters['stage'] ?? null, fn ($q, $value) => $q->where('applicant_stage', $value))
            ->when($filters['position'] ?? null, fn ($q, $value) => $q->where('position', $value));

        return Inertia::render('applicants/index', [
            'applications' => $query->latest()->paginate(15)->withQueryString(), 'filters' => $filters,
            'summary' => ['total' => JobApplication::query()->count(), 'for_screening' => JobApplication::query()->where('applicant_stage', 'for_screening')->count(), 'for_final_interview' => JobApplication::query()->where('applicant_stage', 'for_final_interview')->count(), 'passed' => JobApplication::query()->where('applicant_stage', 'passed')->count()],
        ]);
    }

    public function update(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'applicant_stage' => ['required', Rule::in(['for_screening', 'for_final_interview', 'passed', 'failed'])],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'applicant_update' => ['nullable', 'string', 'max:3000'],
        ]);
        $legacyStatuses = match ($data['applicant_stage']) {
            'for_screening' => ['screening_status' => 'pending', 'interview_status' => 'pending', 'application_status' => 'in_review'],
            'for_final_interview' => ['screening_status' => 'passed', 'interview_status' => 'scheduled', 'application_status' => 'for_interview'],
            'passed' => ['screening_status' => 'passed', 'interview_status' => 'passed', 'application_status' => 'hired'],
            'failed' => ['screening_status' => 'failed', 'interview_status' => 'failed', 'application_status' => 'rejected'],
        };
        $application->update([...$data, ...$legacyStatuses, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('status', 'Applicant status updated.');
    }

    public function resume(JobApplication $application): BinaryFileResponse
    {
        abort_unless($application->resume_disk && $application->resume_path, 404);
        $disk = Storage::disk($application->resume_disk);
        abort_unless($disk->exists($application->resume_path), 404);

        return response()->download($disk->path($application->resume_path), $application->resume_original_name, ['Cache-Control' => 'private, no-store']);
    }
}
