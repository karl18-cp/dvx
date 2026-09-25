<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_email_goes_to_new_employee_with_matching_temporary_password(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'test-only', 'app.url' => 'https://dvx.test']);
        \Illuminate\Support\Facades\Mail::fake();
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $this->actingAs($admin)->post(route('employees.store'), $this->payload())->assertRedirect(route('employees'));
        $employee = User::where('username', 'DVX002')->firstOrFail();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmployeeWelcome::class, function ($mail) use ($employee) {
            $this->assertTrue($mail->hasTo('jamie@divertex.test'));
            $this->assertSame('DVX002', $mail->employeeId);
            $this->assertTrue(Hash::check($mail->temporaryPassword, $employee->password));
            $this->assertSame($employee->username, $mail->temporaryPassword);
            $this->assertSame('https://dvx.test/login', $mail->loginUrl);
            $this->assertStringContainsString('Password &amp; security', $mail->render());
            $this->assertNotNull($employee->faceCredential);

            return true;
        });
        \Illuminate\Support\Facades\Mail::assertSentCount(1);
    }

    public function test_failed_welcome_email_keeps_account_and_reports_failure_without_exposing_credentials(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'test-only']);
        \Illuminate\Support\Facades\Mail::shouldReceive('to')->once()->with('jamie@divertex.test')->andReturnSelf();
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $this->actingAs($admin)->post(route('employees.store'), $this->payload())->assertRedirect()->assertSessionHas('status', fn ($message) => str_contains($message, 'welcome email could not be sent'));
        $employee = User::where('username', 'DVX002')->firstOrFail();
        $this->assertNotNull($employee->personalInformation);
        $this->assertNotNull($employee->faceCredential);
    }

    public function test_invalid_onboarding_never_sends_credentials(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'test-only']);
        \Illuminate\Support\Facades\Mail::fake();
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $this->actingAs($admin)->post(route('employees.store'), $this->payload(['face_descriptor' => []]))->assertSessionHasErrors('face_descriptor');
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_trainee_onboarding_requires_an_active_campaign_and_enrolls_their_face(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $campaign = \App\Models\Campaign::create(['name' => 'Trainee campaign', 'abbreviation' => 'TC', 'is_active' => false]);
        $this->actingAs($admin)->post(route('employees.store'), $this->payload(['position' => 'Trainee']))->assertSessionHasErrors('training_campaign_id');
        $this->post(route('employees.store'), $this->payload(['position' => 'Trainee', 'training_campaign_id' => $campaign->id]))->assertSessionHasErrors('training_campaign_id');
        $campaign->update(['is_active' => true]);
        $this->post(route('employees.store'), $this->payload(['position' => 'Trainee', 'training_campaign_id' => $campaign->id, 'training_status' => 'graduated']))->assertRedirect(route('trainees'));
        $trainee = User::where('username', 'DVXTR001')->firstOrFail();
        $this->assertSame('trainee', $trainee->role);
        $this->assertTrue(Hash::check('DVXTR001', $trainee->password));
        $this->assertSame('in_training', $trainee->training_status);
        $this->assertEquals($campaign->id, $trainee->training_campaign_id);
        $this->assertNotNull($trainee->faceCredential);
        $this->assertNotNull($trainee->personalInformation);
    }

    public function test_an_admin_can_atomically_create_an_employee_with_personal_and_face_records(): void
    {
        $admin = User::factory()->create([
            'username' => 'DVX001',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('employees.store'), $this->payload());

        $response->assertRedirect(route('employees'));

        $employee = User::query()->where('username', 'DVX002')->firstOrFail();

        $this->assertSame('Jamie Rivera', $employee->name);
        $this->assertSame('agent', $employee->role);
        $this->assertTrue(Hash::check('DVX002', $employee->password));
        $this->assertSame('jamie@divertex.test', $employee->personalInformation->email);
        $this->assertCount(128, $employee->faceCredential->encrypted_descriptor);

        $this->assertDatabaseHas('personal_information', [
            'user_id' => $employee->id,
            'phone' => '09171234567',
        ]);
        $this->assertDatabaseHas('face_credentials', [
            'user_id' => $employee->id,
            'model_version' => 'human-3.3.6-faceres',
        ]);
    }

    public function test_invalid_face_enrollment_creates_no_employee_records(): void
    {
        $admin = User::factory()->create([
            'username' => 'DVX001',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('employees.store'), $this->payload([
                'face_descriptor' => [0.1, 0.2],
                'face_liveness' => 0.2,
            ]));

        $response->assertSessionHasErrors([
            'face_descriptor',
            'face_liveness',
        ]);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_information', 0);
        $this->assertDatabaseCount('face_credentials', 0);
    }

    public function test_admin_can_edit_and_suspend_an_employee_and_renew_their_password(): void
    {
        $admin = User::factory()->create([
            'username' => 'DVX001',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('employees.store'), $this->payload())
            ->assertRedirect(route('employees'));

        $employee = User::query()->where('username', 'DVX002')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'full_name' => 'Jamie Rivera Updated',
                'position' => 'IT Support',
                'email' => 'jamie.updated@divertex.test',
                'status' => 'suspended',
                'new_password' => 'Renewed2026',
                'birth_date' => '1995-06-15',
                'start_date' => '2026-08-03',
                'gender' => 'Female',
                'civil_status' => 'Single',
                'phone' => '09170000000',
                'address' => 'Makati City',
                'emergency_contact_name' => 'Alex Rivera',
                'emergency_contact_relationship' => 'Sibling',
                'emergency_contact_phone' => '09179876543',
                'emergency_contact_address' => 'Quezon City',
            ])
            ->assertRedirect(route('employees'));

        $employee->refresh();

        $this->assertSame('Jamie Rivera Updated', $employee->name);
        $this->assertSame('it_support', $employee->role);
        $this->assertSame('suspended', $employee->status);
        $this->assertTrue(Hash::check('Renewed2026', $employee->password));
        $this->assertSame('Makati City', $employee->personalInformation->address);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'DVX002',
            'password' => 'Renewed2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Jamie Rivera',
            'position' => 'Agent',
            'email' => 'jamie@divertex.test',
            'birth_date' => '1995-06-15',
            'start_date' => '2026-08-03',
            'gender' => 'Female',
            'civil_status' => 'Single',
            'phone' => '09171234567',
            'address' => 'Quezon City',
            'emergency_contact_name' => 'Alex Rivera',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '09179876543',
            'emergency_contact_address' => 'Quezon City',
            'face_descriptor' => array_fill(0, 128, 0.125),
            'face_liveness' => 0.91,
            'face_antispoof' => 0.94,
            'face_model_version' => 'human-3.3.6-faceres',
            'face_consent' => true,
        ], $overrides);
    }
}
