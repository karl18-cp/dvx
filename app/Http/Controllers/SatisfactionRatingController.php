<?php

namespace App\Http\Controllers;

use App\Models\SatisfactionRating;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SatisfactionRatingController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', 'string', 'max:50']]);
        $search = trim($filters['search'] ?? '');
        $like = '%'.addcslashes($search, '%_\\').'%';

        return Inertia::render('satisfaction-results', [
            'employees' => User::orderBy('name')->get(['id', 'name', 'username', 'role']),
            'roles' => SatisfactionRating::select('employee_role')->distinct()->orderBy('employee_role')->pluck('employee_role'),
            'records' => SatisfactionRating::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('employee_name', 'like', $like)->orWhere('employee_username', 'like', $like)->orWhere('reviewer_name', 'like', $like)))
                ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('employee_role', $role))
                ->latest('id')->paginate(20)->withQueryString(),
            'filters' => ['search' => $search, 'role' => $filters['role'] ?? ''],
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $rules = ['request_id' => ['required', 'uuid'], 'employee_id' => ['required', 'integer', 'exists:users,id'], 'comments' => ['nullable', 'string', 'max:5000']];
        foreach (SatisfactionRating::CATEGORIES as $category) {
            $rules[$category] = ['required', 'integer', 'between:1,5'];
        }
        $data = $request->validate($rules);
        $scores = array_map(fn ($category) => (int) $data[$category], SatisfactionRating::CATEGORIES);
        $employee = User::findOrFail($data['employee_id']);
        $teams = Team::with('campaign:id,name')
            ->where(fn ($q) => $q->whereHas('members', fn ($q) => $q->where('user_id', $employee->id))
                ->orWhereHas('leaderAssignment', fn ($q) => $q->where('user_id', $employee->id)))
            ->orderBy('name')->get()->map(fn ($team) => ['name' => $team->name, 'campaign' => $team->campaign?->name])->all();
        DB::transaction(function () use ($request, $data, $employee, $teams, $scores) {
            $rating = SatisfactionRating::firstOrCreate(['request_id' => $data['request_id']], [
                'employee_id' => $employee->id, 'rated_by' => $request->user()->id,
                'employee_name' => $employee->name, 'employee_username' => $employee->username, 'employee_role' => $employee->role,
                'reviewer_name' => $request->user()->name, 'reviewer_username' => $request->user()->username,
                'teams' => $teams, ...array_combine(SatisfactionRating::CATEGORIES, $scores),
                'average' => round(array_sum($scores) / count($scores), 2), 'comments' => $data['comments'] ?? null,
            ]);
            $sameScores = collect(SatisfactionRating::CATEGORIES)->every(fn ($category) => (int) $data[$category] === $rating->$category);
            if ($rating->employee_id !== $employee->id || $rating->rated_by !== $request->user()->id || ! $sameScores || $rating->comments !== ($data['comments'] ?? null)) {
                throw ValidationException::withMessages(['request_id' => 'This submission has already been used. Reopen the rating modal to submit a new rating.']);
            }
            if ($rating->wasRecentlyCreated) {
                DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'Satisfaction Rating Submitted', 'target_type' => SatisfactionRating::class, 'target_id' => $rating->id, 'metadata' => json_encode(['employee_id' => $employee->id, 'average' => $rating->average]), 'created_at' => now()]);
            }
        }, attempts: 3);

        return to_route('satisfaction-results')->with('status', 'Rating submitted and saved to satisfaction results.');
    }
}
