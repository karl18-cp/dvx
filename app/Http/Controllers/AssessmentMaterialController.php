<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentTrainingMaterial;
use App\Services\AssessmentMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentMaterialController extends Controller
{
    private const MIME_TYPES = ['video/mp4', 'video/webm', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    private const EXTENSIONS = ['mp4', 'webm', 'mp3', 'm4a', 'ogg', 'jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'];

    public function store(Request $request, Assessment $assessment, AssessmentMediaStorage $storage): RedirectResponse
    {
        $data = $this->validateData($request);
        $metadata = [];
        if ($data['type'] !== 'written') {
            $metadata = $storage->store($request->file('file'), $assessment->id);
        }
        try {
            DB::transaction(function () use ($request, $assessment, $data, $metadata): void {
                $assessment->trainingMaterials()->create([
                    ...$data, ...$metadata, 'created_by' => $request->user()->id,
                    'display_order' => (int) $assessment->trainingMaterials()->max('display_order') + 1,
                ]);
            });
        } catch (\Throwable $error) {
            $storage->delete($metadata['storage_disk'] ?? null, $metadata['storage_key'] ?? null);
            throw $error;
        }

        return back()->with('status', 'Training material added.');
    }

    public function update(Request $request, Assessment $assessment, AssessmentTrainingMaterial $material, AssessmentMediaStorage $storage): RedirectResponse
    {
        $this->belongsTo($assessment, $material);
        $data = $this->validateData($request, true);
        $metadata = [];
        if ($request->hasFile('file')) {
            $metadata = $storage->store($request->file('file'), $assessment->id);
        }
        $old = [$material->storage_disk, $material->storage_key];
        try {
            DB::transaction(fn () => $material->update([...$data, ...$metadata]));
        } catch (\Throwable $error) {
            $storage->delete($metadata['storage_disk'] ?? null, $metadata['storage_key'] ?? null);
            throw $error;
        }
        if ($metadata) {
            $storage->delete($old[0], $old[1]);
        }

        return back()->with('status', 'Training material updated.');
    }

    public function destroy(Assessment $assessment, AssessmentTrainingMaterial $material, AssessmentMediaStorage $storage): RedirectResponse
    {
        $this->belongsTo($assessment, $material);
        if ($material->trainingProgress()->exists()) {
            $material->update(['is_active' => false]);
        } else {
            $material->delete();
            $storage->delete($material->storage_disk, $material->storage_key);
        }

        return back()->with('status', 'Training material removed.');
    }

    public function reorder(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate(['material_ids' => ['required', 'array'], 'material_ids.*' => ['integer', 'distinct']]);
        $valid = $assessment->trainingMaterials()->whereIn('id', $data['material_ids'])->count();
        abort_unless($valid === count($data['material_ids']), 422);
        DB::transaction(fn () => collect($data['material_ids'])->each(fn ($id, $order) => $assessment->trainingMaterials()->whereKey($id)->update(['display_order' => $order + 1])));

        return back();
    }

    public function show(AssessmentTrainingMaterial $material): StreamedResponse
    {
        abort_unless($material->is_active && $material->storage_disk && $material->storage_key, 404);
        $disk = Storage::disk($material->storage_disk);
        abort_unless($disk->exists($material->storage_key), 404);

        return response()->streamDownload(fn () => fpassthru($disk->readStream($material->storage_key)), $material->original_filename, [
            'Content-Type' => $material->mime_type, 'Content-Length' => (string) $material->file_size,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $material->original_filename).'"',
        ]);
    }

    private function validateData(Request $request, bool $updating = false): array
    {
        $type = $request->input('type');

        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['video', 'audio', 'image', 'document', 'written'])],
            'content' => [Rule::requiredIf($type === 'written'), 'nullable', 'string', 'max:100000'],
            'file' => [Rule::requiredIf(! $updating && $type !== 'written'), 'nullable', 'file', 'max:'.config('assessments.max_upload_kilobytes'), 'mimetypes:'.implode(',', self::MIME_TYPES), 'extensions:'.implode(',', self::EXTENSIONS)],
            'is_required' => ['required', 'boolean'], 'required_completion_percentage' => ['required', 'integer', 'between:0,100'],
        ]);
    }

    private function belongsTo(Assessment $assessment, AssessmentTrainingMaterial $material): void
    {
        abort_unless($material->assessment_id === $assessment->id, 404);
    }
}
