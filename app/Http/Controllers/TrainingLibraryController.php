<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use App\Models\Campaign;
use App\Models\TrainingLibraryMaterial;
use App\Services\AssessmentMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingLibraryController extends Controller
{
    private const TYPES = ['video', 'audio', 'image', 'document', 'written'];

    public function index(Request $request): Response
    {
        $f = $request->only(['search', 'category', 'skill', 'type', 'status', 'campaign']);
        $target = $request->integer('assessment') ? Assessment::query()->with('campaigns:id')->find($request->integer('assessment')) : null;
        $items = TrainingLibraryMaterial::query()->with(['skill:id,name', 'campaigns:id,name,abbreviation'])->withCount('attachments')->when($f['search'] ?? null, fn ($q, $v) => $q->where('title', 'like', '%'.addcslashes($v, '%_\\').'%'))->when($f['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))->when($f['skill'] ?? null, fn ($q, $v) => $q->where('skill_id', $v))->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($f['campaign'] ?? null, fn ($q, $v) => $q->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($c) => $c->whereKey($v))))
            ->when($target && ! $target->applies_to_all_campaigns, fn ($q) => $q->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($c) => $c->whereIn('campaigns.id', $target->campaigns->modelKeys()))))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('assessments/training-library', ['materials' => $items, 'filters' => $f, 'target_assessment_id' => $request->integer('assessment') ?: null, 'categories' => AssessmentCategory::query()->where('is_active', true)->get(['id', 'name']), 'skills' => AssessmentSkill::query()->where('is_active', true)->get(['id', 'name']), 'campaigns' => Campaign::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation'])]);
    }

    public function store(Request $request, AssessmentMediaStorage $storage): RedirectResponse
    {
        $data = $this->validateData($request);
        $meta = $data['type'] === 'written' ? [] : $storage->store($request->file('file'), 0);
        try {
            DB::transaction(function () use ($data, $meta, $request): void {
                $campaignIds = $data['campaign_ids'] ?? [];
                unset($data['campaign_ids']);
                $material = TrainingLibraryMaterial::query()->create([...$data, ...$meta, 'status' => 'active', 'created_by' => $request->user()->id]);
                $material->campaigns()->sync($material->applies_to_all_campaigns ? [] : $campaignIds);
            });
        } catch (\Throwable $e) {
            $storage->delete($meta['storage_disk'] ?? null, $meta['storage_key'] ?? null);
            throw $e;
        }

        return back()->with('status', 'Training material created.');
    }

    public function update(Request $request, TrainingLibraryMaterial $material): RedirectResponse
    {
        abort_if($request->hasFile('file') && $material->attachments()->exists(), 422, 'Create a new material before replacing media that is already attached.');
        $material->update($request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'category_id' => ['nullable', 'exists:assessment_categories,id'], 'skill_id' => ['nullable', 'exists:assessment_skills,id'], 'duration_seconds' => ['nullable', 'integer', 'min:0']]));

        return back();
    }

    public function duplicate(Request $request, TrainingLibraryMaterial $material): RedirectResponse
    {
        $copy = $material->replicate(['status', 'created_by']);
        $copy->title = 'Copy of '.$material->title;
        $copy->status = 'active';
        $copy->created_by = $request->user()->id;
        $copy->save();

        return back();
    }

    public function archive(TrainingLibraryMaterial $material): RedirectResponse
    {
        $material->update(['status' => 'archived']);

        return back();
    }

    public function destroy(TrainingLibraryMaterial $material, AssessmentMediaStorage $storage): RedirectResponse
    {
        abort_if($material->attachments()->exists(), 422, 'Used materials must be archived.');
        $material->delete();
        $storage->delete($material->storage_disk, $material->storage_key);

        return back();
    }

    public function preview(TrainingLibraryMaterial $material): StreamedResponse
    {
        abort_unless($material->storage_disk && $material->storage_key, 404);
        $disk = Storage::disk($material->storage_disk);
        abort_unless($disk->exists($material->storage_key), 404);

        return response()->streamDownload(fn () => fpassthru($disk->readStream($material->storage_key)), $material->original_filename, ['Content-Type' => $material->mime_type, 'Content-Disposition' => 'inline']);
    }

    private function validateData(Request $request): array
    {
        $type = $request->input('type');

        return $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'type' => ['required', Rule::in(self::TYPES)], 'category_id' => ['nullable', 'exists:assessment_categories,id'], 'skill_id' => ['nullable', 'exists:assessment_skills,id'], 'content' => [Rule::requiredIf($type === 'written'), 'nullable', 'string', 'max:100000'], 'duration_seconds' => ['nullable', 'integer', 'min:0'], 'file' => [Rule::requiredIf($type !== 'written'), 'nullable', 'file', 'max:'.config('assessments.max_upload_kilobytes')], 'applies_to_all_campaigns' => ['required', 'boolean'], 'campaign_ids' => ['required_if:applies_to_all_campaigns,false', 'array'], 'campaign_ids.*' => ['integer', 'distinct', Rule::exists('campaigns', 'id')->where('is_active', true)]]);
    }
}
