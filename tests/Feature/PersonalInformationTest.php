<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_information_belongs_to_exactly_one_user(): void
    {
        $user = User::factory()->create();

        $personalInformation = $user->personalInformation()->create([
            'email' => 'employee-one@divertex.test',
            'birth_date' => '1995-06-15',
            'start_date' => '2026-08-01',
            'gender' => 'Female',
            'civil_status' => 'Single',
            'phone' => '+639000000000',
            'address' => 'Test address',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '+639000000001',
            'emergency_contact_address' => 'Emergency address',
        ]);

        $this->assertTrue($personalInformation->user->is($user));
        $this->assertTrue($user->fresh()->personalInformation->is($personalInformation));
    }

    public function test_personal_information_is_deleted_with_its_user(): void
    {
        $user = User::factory()->create();

        $personalInformation = $user->personalInformation()->create([
            'email' => 'employee-two@divertex.test',
            'birth_date' => '1995-06-15',
            'start_date' => '2026-08-01',
            'gender' => 'Female',
            'civil_status' => 'Single',
            'phone' => '+639000000000',
            'address' => 'Test address',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '+639000000001',
        ]);

        $user->delete();

        $this->assertDatabaseMissing('personal_information', [
            'id' => $personalInformation->id,
        ]);
    }
}
