<?php

namespace App\Http\Controllers;

use App\Models\PublicUpdate;
use App\Services\ApplicantExamService;
use App\Services\PublicFormService;
use Inertia\Inertia;
use Inertia\Response;

class CareersController extends Controller
{
    public function __invoke(PublicFormService $forms, ApplicantExamService $exams): Response
    {
        $updates = PublicUpdate::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['approvedComments' => fn ($query) => $query->latest()->limit(8)])
            ->withCount('approvedComments')
            ->latest('published_at')
            ->limit(8)
            ->get()
            ->map(fn (PublicUpdate $update) => [
                'id' => $update->id,
                'title' => $update->title,
                'body' => $update->body,
                'author_name' => $update->author_name,
                'published_at' => $update->published_at?->toISOString(),
                'comments_count' => $update->approved_comments_count,
                'comments' => $update->approvedComments->sortBy('created_at')->values()->map(fn ($comment) => [
                    'id' => $comment->id,
                    'name' => $comment->name,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toISOString(),
                ]),
            ]);

        return Inertia::render('welcome', [
            'applicantExam' => $exams->publicSettings(),
            'publicForms' => [
                'application' => $forms->definition('application'),
                'business' => $forms->definition('business'),
            ],
            'publicUpdates' => $updates,
        ]);
    }
}
