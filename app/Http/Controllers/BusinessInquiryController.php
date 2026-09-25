<?php

namespace App\Http\Controllers;

use App\Models\BusinessInquiry;
use App\Services\BusinessInquiryEmailService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BusinessInquiryController extends Controller
{
    public const SERVICES = ['Customer Support', 'Development Teams', 'Quality Assurance', 'Virtual Assistance', 'Digital Marketing', 'Data & Analytics', 'Other'];

    public function store(Request $request, BusinessInquiryEmailService $email)
    {
        $data = app(\App\Services\PublicFormService::class)->submission($request, 'business', [
            'submission_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:150'],
            'company' => ['required', 'string', 'max:200'], 'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'service' => ['required', Rule::in(self::SERVICES)],
            'message' => ['required', 'string', 'max:5000'], 'meeting_time' => ['required', 'date_format:Y-m-d\TH:i'],
            'timezone' => ['required', 'timezone', 'max:100'], 'website' => ['nullable', 'max:0'],
        ]);
        $meeting = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['meeting_time'], $data['timezone']);
        if ($meeting->format('Y-m-d\TH:i') !== $data['meeting_time'] || $meeting->isPast()) {
            throw ValidationException::withMessages(['meeting_time' => 'Choose a future meeting date and time in your selected timezone.']);
        }
        unset($data['meeting_time'], $data['website']);
        $inquiry = BusinessInquiry::firstOrCreate(['submission_id' => $data['submission_id']], [...$data, 'meeting_at' => $meeting->utc()]);
        if ($inquiry->wasRecentlyCreated) $email->send($inquiry);
        return back()->with('inquiry_success', true);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin' && $request->user()->status === 'active', 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', Rule::in(['new', 'contacted', 'meeting_scheduled', 'closed'])]]);
        $query = BusinessInquiry::query()->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        return Inertia::render('business-inquiries', ['inquiries' => $query->latest()->paginate(15)->withQueryString(), 'filters' => $filters, 'statusMessage' => $request->session()->get('status')]);
    }

    public function update(Request $request, BusinessInquiry $inquiry)
    {
        $this->authorizeAdmin($request);
        $inquiry->update($request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'meeting_scheduled', 'closed'])], 'admin_notes' => ['nullable', 'string', 'max:5000']]));
        return back()->with('status', 'Inquiry updated. Contact the client directly to confirm meeting arrangements.');
    }

    public function retry(Request $request, BusinessInquiry $inquiry, BusinessInquiryEmailService $email)
    {
        $this->authorizeAdmin($request);
        return back()->with('status', $email->send($inquiry) ? 'Inquiry email sent.' : 'Email could not be sent. The inquiry is saved; check mail settings and retry.');
    }
}
