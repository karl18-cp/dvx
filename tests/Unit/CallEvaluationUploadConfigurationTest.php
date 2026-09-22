<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CallEvaluationUploadConfigurationTest extends TestCase
{
    public function test_project_php_runtime_accepts_the_configured_recording_limit(): void
    {
        $ini = file_get_contents(dirname(__DIR__, 2).'/.php-runtime/php.ini');

        $this->assertStringContainsString('upload_max_filesize=250M', $ini);
        $this->assertStringContainsString('post_max_size=260M', $ini);
        $this->assertStringContainsString('upload_tmp_dir=', $ini);
    }
}
