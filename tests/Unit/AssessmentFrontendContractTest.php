<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AssessmentFrontendContractTest extends TestCase
{
    public function test_employee_assessment_start_displays_progress_and_server_validation_errors(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/index.tsx');

        $this->assertStringContainsString('role="alert"', $source);
        $this->assertStringContainsString("'Starting...'", $source);
        $this->assertStringContainsString('onStart:', $source);
        $this->assertStringContainsString('onFinish:', $source);
    }

    public function test_autosave_uses_background_fetch_instead_of_inertia_navigation(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/take.tsx');

        $this->assertIsString($source);
        $this->assertStringContainsString('await fetch(', $source);
        $this->assertStringContainsString("'X-XSRF-TOKEN'", $source);
        $this->assertDoesNotMatchRegularExpression('/router\.(put|patch|visit)\([\s\S]{0,200}attempts\/\$\{attempt\.id\}\/answer/', $source);
    }

    public function test_take_page_exposes_flagging_and_a_final_review_summary(): void
    {
        $take = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/take.tsx');
        $review = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/review.tsx');

        $this->assertIsString($take);
        $this->assertIsString($review);
        $this->assertStringContainsString('Flag for Review', $take);
        $this->assertStringContainsString('Review Assessment', $take);
        $this->assertStringContainsString('pendingAnswer', $take);
        $this->assertStringContainsString('flagged_question_ids', $take);
        $this->assertStringContainsString('Final Review', $review);
        $this->assertStringContainsString('Unanswered', $review);
        $this->assertStringContainsString('Review Question', $review);
        $this->assertStringContainsString('Submit assessment?', $review);
        $this->assertStringContainsString('`/assessments/attempts/${attempt.id}/submit`', $review);
    }

    public function test_training_progress_updates_use_background_requests_with_error_feedback(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/training.tsx');

        $this->assertIsString($source);
        $this->assertStringContainsString('await fetch(', $source);
        $this->assertStringContainsString('Please retry', $source);
        $this->assertStringNotContainsString('router.post(', $source);
        $this->assertStringContainsString('method="post"', $source);
        $this->assertStringContainsString("'Take Assessment'", $source);
        $this->assertStringContainsString('requiredTrainingComplete &&', $source);
    }

    public function test_builder_locks_true_false_choices_to_a_radio_selection(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/builder.tsx');

        $this->assertIsString($source);
        $this->assertStringContainsString("option_text: 'True'", $source);
        $this->assertStringContainsString("option_text: 'False'", $source);
        $this->assertStringContainsString('<RadioGroup', $source);
        $this->assertStringContainsString("question.data.question_type !== 'true_false'", $source);
    }

    public function test_assignment_ui_has_independent_today_actions_and_safe_row_actions(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/assignments.tsx');

        $this->assertIsString($source);
        $this->assertSame(4, substr_count($source, '<DateField'));
        $this->assertStringContainsString('Edit Assignment', $source);
        $this->assertStringContainsString('Delete Assignment?', $source);
        $this->assertStringContainsString('attempts_count', $source);
        $this->assertStringContainsString('training_progress_count', $source);
        $this->assertStringContainsString("timeZone: 'Asia/Manila'", $source);
        $this->assertStringContainsString('formatToParts', $source);
    }

    public function test_employee_assignment_dates_preserve_admin_wall_clock_time(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/index.tsx');

        $this->assertIsString($source);
        $this->assertMatchesRegularExpression('/assignmentDateTime\(\s*a\.available_from,?\s*\)/', $source);
        $this->assertMatchesRegularExpression('/assignmentDateTime\(\s*a\.due_date,?\s*\)/', $source);
        $this->assertStringContainsString("timeZone: 'Asia/Manila'", $source);
        $this->assertStringContainsString('a.due_label', $source);
    }

    public function test_assessment_management_uses_compact_type_and_back_navigation(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/management.tsx');
        $breadcrumbs = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/breadcrumbs.tsx');

        $this->assertIsString($source);
        $this->assertStringContainsString('p-4 lg:p-6', $source);
        $this->assertStringContainsString("{ title: 'Back', href: '#back' }", $source);
        $this->assertStringContainsString('window.history.back()', $breadcrumbs);
        $this->assertStringContainsString("router.visit('/dashboard')", $breadcrumbs);
    }

    public function test_all_assessment_admin_sections_share_compact_typography(): void
    {
        $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertIsString($styles);
        $this->assertStringContainsString('.assessment-admin h1', $styles);
        $this->assertStringContainsString('.assessment-admin h2', $styles);
        $this->assertStringContainsString('.assessment-admin .MuiInputBase-root', $styles);
        $this->assertStringContainsString('font-size: 10.86px !important', $styles);
        $this->assertStringContainsString('font-weight: 500', $styles);
    }
}
