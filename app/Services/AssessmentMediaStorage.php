<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssessmentMediaStorage
{
    public function store(UploadedFile $file, int $assessmentId): array
    {
        $disk = (string) config('assessments.media_disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension());
        $storedFilename = Str::uuid()->toString().'.'.$extension;
        $key = $file->storeAs("assessments/{$assessmentId}", $storedFilename, $disk);
        throw_if($key === false, \RuntimeException::class, 'Training file could not be stored.');

        return [
            'storage_disk' => $disk, 'storage_key' => $key,
            'original_filename' => basename($file->getClientOriginalName()),
            'stored_filename' => $storedFilename, 'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function delete(?string $disk, ?string $key): void
    {
        if ($disk && $key) {
            Storage::disk($disk)->delete($key);
        }
    }

    public function temporaryUrl(string $disk, string $key): string
    {
        $storage = Storage::disk($disk);

        return method_exists($storage, 'temporaryUrl')
            ? $storage->temporaryUrl($key, now()->addMinutes(10))
            : route('assessments.media.show', ['material' => $key]);
    }
}
