<?php

namespace Tests\Feature;

use App\Models\CallEvaluationScorecard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_training_and_quality_routes_preserve_authorization(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $scorecard = CallEvaluationScorecard::query()->create(['name' => 'Navigation QA', 'applies_to_all_campaigns' => true, 'status' => 'draft', 'passing_score' => 80, 'created_by' => $admin->id]);

        foreach ([route('assessments.manage'), route('assessments.assignments'), route('assessments.schedule'), route('assessments.question-bank'), route('training-library.index'), route('coaching.manage.index'), route('assessments.reports.results'), route('quality.scorecards.index'), route('quality.scorecards.builder', $scorecard), route('quality.scorecards.preview', $scorecard)] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($employee)->get($url)->assertForbidden();
        }
    }

    public function test_sidebar_source_separates_navigation_groups_without_duplicate_feature_links(): void
    {
        $source = file_get_contents(resource_path('js/components/app-sidebar.tsx'));

        $this->assertStringContainsString('label="Management"', $source);
        $this->assertStringContainsString('label="Training & Development"', $source);
        $this->assertStringContainsString('label="Quality Assurance"', $source);
        $this->assertSame(1, substr_count($source, "label: 'QA Scorecards'"));
        $this->assertSame(1, substr_count($source, "label: 'Training Library'"));
        $this->assertSame(0, substr_count($source, "label: 'Question Bank'"));
        $this->assertSame(1, substr_count($source, "label: 'Coaching Log'"));
        $this->assertSame(1, substr_count($source, "label: 'QA Dashboard'"));
        $this->assertSame(0, substr_count($source, "label: 'Schedule'"));
        $this->assertStringContainsString("label: 'Campaign'", $source);

        $assignments = file_get_contents(resource_path('js/pages/assessments/assignments.tsx'));
        $this->assertStringContainsString('href="/management/assessments/schedule"', $assignments);

        $trainingLibrary = file_get_contents(resource_path('js/pages/assessments/training-library.tsx'));
        $this->assertStringContainsString('href="/management/question-bank"', $trainingLibrary);
    }
}
