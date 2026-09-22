<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SimpleAssessmentFrontendContractTest extends TestCase
{
    public function test_simple_create_only_exposes_prompt_fields_and_two_training_choices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/simple-create.tsx');
        foreach (['Assessment Title', 'All Campaigns', 'Description (optional)', 'Select from Training Library', 'Upload Training Material', 'Required before taking Assessment', 'Number of Questions', 'Total Points', 'Passing Score (%)', 'Time Limit (minutes)', 'Maximum Attempts'] as $label) {
            $this->assertStringContainsString($label, $source);
        }
        foreach (['No training material', 'Random Question Pools', 'Difficulty distribution', 'Snapshot settings'] as $advanced) {
            $this->assertStringNotContainsString($advanced, $source);
        }
    }

    public function test_simple_builder_has_required_types_navigation_progress_save_and_review(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/assessments/simple-builder.tsx');
        foreach (['multiple_choice', 'true_false', 'multiple_selection', 'short_answer', 'Questions Ready:', 'navigate(index)', 'Save & Next', 'recentlySuccessful', 'Requires Manual Review', 'Choose from Question Bank', 'Advanced Builder', 'Review Assessment'] as $contract) {
            $this->assertStringContainsString($contract, $source);
        }
        $this->assertStringContainsString("option_text: 'True'", str_replace('"', "'", $source));
        $this->assertStringContainsString("option_text: 'False'", str_replace('"', "'", $source));
    }
}
