<?php

namespace App\Http\Controllers;

use App\Models\EmployeeForm;
use App\Models\EmployeeFormResponse;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeFormController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    private function assignedTeams(Request $request)
    {
        if ($request->user()->role === 'team_leader') {
            return app(\App\Services\TeamLeaderWorkspaceService::class)->teams($request->user());
        }
        return Team::query()->where(fn ($q) => $q
            ->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orWhereHas('leaderAssignment', fn ($q) => $q->where('user_id', $request->user()->id)));
    }

    public function index(Request $request)
    {
        $this->admin($request);
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['manage', 'responses'])],
            'search' => ['nullable', 'string', 'max:100'],
            'form_id' => ['nullable', 'integer', 'exists:employee_forms,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $search = trim($filters['search'] ?? '');
        $like = '%'.addcslashes($search, '%_\\').'%';
        $responseQuery = EmployeeFormResponse::query()
            ->when($filters['form_id'] ?? null, fn ($q, $id) => $q->where('employee_form_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', Carbon::parse($date, 'Asia/Manila')->startOfDay()->setTimezone(config('app.timezone'))))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', Carbon::parse($date, 'Asia/Manila')->endOfDay()->setTimezone(config('app.timezone'))))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('employee_name', 'like', $like)->orWhere('employee_username', 'like', $like)->orWhere('form_title', 'like', $like)));

        return Inertia::render('forms/manage', [
            'forms' => EmployeeForm::with('teams:id,name,campaign_id', 'teams.campaign:id,name')->withCount('responses')
                ->when($search !== '' && ($filters['tab'] ?? 'manage') === 'manage', fn ($q) => $q->where('title', 'like', $like))
                ->latest('id')->paginate(15, ['*'], 'forms_page')->withQueryString(),
            'responses' => ($filters['tab'] ?? 'manage') === 'responses'
                ? $responseQuery->latest('id')->paginate(20)->withQueryString() : null,
            'formOptions' => EmployeeForm::withTrashed()->orderBy('title')->get(['id', 'title', 'deleted_at']),
            'teams' => Team::with('campaign:id,name')->orderBy('name')->get(['id', 'name', 'campaign_id']),
            'stats' => ['total' => EmployeeForm::count(), 'active' => EmployeeForm::where('status', 'active')->count(), 'responses' => EmployeeFormResponse::count()],
            'filters' => ['tab' => $filters['tab'] ?? 'manage', 'search' => $search, 'form_id' => $filters['form_id'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    private function formData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'ranking_enabled' => ['required', 'boolean'],
            'team_ids' => ['present', 'array', 'max:200', $request->input('status') === 'active' ? 'min:1' : 'min:0'],
            'team_ids.*' => ['required', 'integer', 'distinct', 'exists:teams,id'],
            'fields' => ['required', 'array', 'min:1', 'max:50'],
            'fields.*' => ['required', 'array:id,label,type,placeholder,required,options'],
            'fields.*.id' => ['required', 'uuid', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:200'],
            'fields.*.type' => ['required', Rule::in(EmployeeForm::TYPES)],
            'fields.*.placeholder' => ['nullable', 'string', 'max:200'],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.options' => ['present', 'array', 'max:100'],
            'fields.*.options.*' => ['required', 'string', 'max:200'],
        ]);
        foreach ($data['fields'] as $index => &$field) {
            if (in_array($field['type'], ['dropdown', 'radio', 'checkbox'])) {
                $options = array_map('trim', $field['options']);
                if (! $options || in_array('', $options, true) || count(array_unique($options)) !== count($options)) {
                    throw ValidationException::withMessages(["fields.$index.options" => 'Enter at least one option; each option must be unique and nonempty.']);
                }
                $field['options'] = $options;
            } else {
                $field['options'] = [];
            }
        }

        return $data;
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->formData($request);
        DB::transaction(function () use ($request, $data) {
            $form = EmployeeForm::create([...collect($data)->except('team_ids')->all(), 'created_by' => $request->user()->id]);
            $form->teams()->sync($data['team_ids']);
        });

        return to_route('forms.index')->with('status', 'Form created. Active forms are available to the assigned teams.');
    }

    public function update(Request $request, EmployeeForm $form)
    {
        $this->admin($request);
        $data = $this->formData($request);
        $request->validate(['revision' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $data, $form) {
            $locked = EmployeeForm::lockForUpdate()->findOrFail($form->id);
            if ($locked->revision !== $request->integer('revision')) {
                throw ValidationException::withMessages(['revision' => 'This form changed. Close the editor, refresh the page, and reopen it.']);
            }
            $locked->update([...collect($data)->except('team_ids')->all(), 'revision' => $locked->revision + 1]);
            $locked->teams()->sync($data['team_ids']);
        });

        return to_route('forms.index')->with('status', 'Form updated. Existing responses keep their original questions and answers.');
    }

    public function destroy(Request $request, EmployeeForm $form)
    {
        $this->admin($request);
        DB::transaction(fn () => EmployeeForm::lockForUpdate()->findOrFail($form->id)->delete());

        return to_route('forms.index')->with('status', 'Form removed. Its responses remain available in the Responses tab.');
    }

    public function mine(Request $request)
    {
        $teams = $this->assignedTeams($request)->pluck('id');

        return Inertia::render('forms/mine', [
            'forms' => EmployeeForm::where('status', 'active')->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teams))
                ->withCount(['responses as my_responses_count' => fn ($q) => $q->where('employee_id', $request->user()->id)])
                ->latest('id')->get(['id', 'title', 'description', 'fields', 'revision']),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function submit(Request $request, EmployeeForm $form)
    {
        $base = $request->validate(['request_id' => ['required', 'uuid'], 'revision' => ['required', 'integer'], 'answers' => ['present', 'array', 'max:50']]);
        $base['revision'] = (int) $base['revision'];
        DB::transaction(function () use ($request, $form, $base) {
            $locked = EmployeeForm::lockForUpdate()->findOrFail($form->id);
            $prior = EmployeeFormResponse::where('request_id', $base['request_id'])->first();
            if ($prior) {
                $priorAnswers = collect($prior->answers)->mapWithKeys(fn ($answer) => [$answer['id'] => $answer['value']])->all();
                $replayedAnswers = collect($prior->answers)->mapWithKeys(fn ($answer) => [$answer['id'] => $base['answers'][$answer['id']] ?? ($answer['type'] === 'checkbox' ? [] : null)])->all();
                if ($prior->employee_id !== $request->user()->id || $prior->employee_form_id !== $form->id || $prior->revision !== $base['revision'] || $priorAnswers != $replayedAnswers || array_diff(array_keys($base['answers']), array_keys($priorAnswers))) {
                    throw ValidationException::withMessages(['request_id' => 'This submission ID has already been used. Reopen the form and try again.']);
                }

                return;
            }
            $teams = $this->assignedTeams($request)->whereHas('forms', fn ($q) => $q->where('employee_forms.id', $form->id))->get(['id', 'name']);
            abort_unless($locked->status === 'active' && $teams->isNotEmpty(), 403);
            if ($locked->revision !== $base['revision']) {
                throw ValidationException::withMessages(['revision' => 'This form was updated. Close it, refresh the page, and reopen the latest version.']);
            }
            if (array_diff(array_keys($base['answers']), array_column($locked->fields, 'id'))) {
                throw ValidationException::withMessages(['answers' => 'Answers contain an unknown field. Reopen the latest form.']);
            }
            $rules = [];
            $attributes = [];
            foreach ($locked->fields as $field) {
                $key = 'answers.'.$field['id'];
                $attributes[$key] = $field['label'];
                $rules[$key] = [$field['required'] ? 'required' : 'nullable'];
                $rules[$key] = [...$rules[$key], ...match ($field['type']) {
                    'number' => ['numeric', 'between:-1000000000000,1000000000000'],
                    'email' => ['string', 'email', 'max:254'],
                    'date' => ['date_format:Y-m-d'],
                    'dropdown', 'radio' => ['string', Rule::in($field['options'])],
                    'checkbox' => ['array', 'max:100', $field['required'] ? 'min:1' : 'min:0'],
                    default => ['string', 'max:5000'],
                }];
                if ($field['type'] === 'checkbox') {
                    $rules[$key.'.*'] = ['string', 'distinct', Rule::in($field['options'])];
                }
            }
            $validated = $request->validate($rules, [], $attributes);
            EmployeeFormResponse::create([
                'request_id' => $base['request_id'], 'employee_form_id' => $form->id,
                'employee_id' => $request->user()->id, 'employee_name' => $request->user()->name, 'employee_username' => $request->user()->username,
                'form_title' => $locked->title, 'form_description' => $locked->description, 'revision' => $locked->revision,
                'ranking_enabled' => $locked->ranking_enabled, 'teams' => $teams->toArray(),
                'points' => $locked->ranking_enabled ? 1 : 0,
                'answers' => array_map(fn ($field) => [...$field, 'value' => $validated['answers'][$field['id']] ?? ($field['type'] === 'checkbox' ? [] : null)], $locked->fields),
            ]);
        }, attempts: 3);

        return to_route('forms.mine')->with('status', 'Your response has been submitted. Thank you.');
    }

    public function ranking(Request $request, \App\Services\EmployeeRankingService $ranking)
    {
        return Inertia::render('forms/ranking', [
            'leaders' => $ranking->leaders(null, $request->user()),
        ]);
    }
}
