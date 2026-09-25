<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_and_attendance_lists_use_the_current_profile_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'PHOTO001']);
        $this->actingAs($user);
        foreach (['first.png', 'replacement.png'] as $name) {
            $this->post('/settings/profile/photo', ['photo' => UploadedFile::fake()->image($name, 100, 100)])->assertSessionHasNoErrors();
            $avatar = $user->fresh()->avatar;
            foreach (['/employees', '/attendance'] as $page) {
                $this->get($page)->assertOk()->assertInertia(fn (AssertableInertia $response) => $response
                    ->where('employees.0.avatar', $avatar)->missing('employees.0.profile_photo_path'));
            }
        }
        $this->delete('/settings/profile/photo')->assertRedirect();
        $this->get('/attendance')->assertInertia(fn (AssertableInertia $response) => $response->where('employees.0.avatar', ''));
    }

    public function test_users_can_upload_replace_and_remove_their_own_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'agent']);
        $this->actingAs($user)->post('/settings/profile/photo', ['photo' => UploadedFile::fake()->image('portrait.png', 200, 200)])->assertSessionHasNoErrors()->assertRedirect('/settings/profile');
        $first = $user->fresh()->profile_photo_path;
        Storage::disk('local')->assertExists($first);
        $avatar = $user->fresh()->avatar;
        $this->assertStringContainsString("/users/$user->id/photo?v=", $avatar);
        $this->assertArrayNotHasKey('profile_photo_path', $user->fresh()->toArray());
        $this->get($avatar)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/settings/profile/photo', ['photo' => UploadedFile::fake()->image('new.jpg', 300, 300)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);
        $second = $user->fresh()->profile_photo_path;
        Storage::disk('local')->assertExists($second);
        $this->assertNotSame($avatar, $user->fresh()->avatar);
        $this->delete('/settings/profile/photo')->assertRedirect('/settings/profile');
        Storage::disk('local')->assertMissing($second);
        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertSame('', $user->fresh()->avatar);
        $this->get("/users/$user->id/photo")->assertNotFound();
    }

    public function test_upload_cannot_modify_another_user_and_guests_are_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->post('/settings/profile/photo', ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertRedirect('/login');
        $this->get("/users/$owner->id/photo")->assertRedirect('/login');
        $this->actingAs($owner)->post('/settings/profile/photo', ['user_id' => $other->id, 'photo' => UploadedFile::fake()->image('photo.jpg', 128, 128)])->assertSessionHasNoErrors();
        $this->assertNull($other->fresh()->profile_photo_path);
        $path = $owner->fresh()->profile_photo_path;
        $this->actingAs($other)->delete('/settings/profile/photo', ['user_id' => $owner->id])->assertRedirect('/settings/profile');
        $this->assertSame($path, $owner->fresh()->profile_photo_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_bad_formats_sizes_and_dimensions_are_rejected_without_losing_existing_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/profile/photo', ['photo' => UploadedFile::fake()->image('photo.png', 100, 100)])->assertSessionHasNoErrors();
        $path = $user->fresh()->profile_photo_path;
        foreach ([UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'), UploadedFile::fake()->create('fake.jpg', 1, 'text/plain'), UploadedFile::fake()->image('large.png', 128, 128)->size(2049), UploadedFile::fake()->image('tiny.png', 32, 32)] as $file) {
            $this->post('/settings/profile/photo', ['photo' => $file])->assertSessionHasErrors('photo');
        }
        $this->assertSame($path, $user->fresh()->profile_photo_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles('profile-photos'));
    }

    public function test_deleting_account_removes_its_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/profile/photo', ['photo' => UploadedFile::fake()->image('photo.png', 100, 100)])->assertSessionHasNoErrors();
        $path = $user->fresh()->profile_photo_path;
        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_password_confirmation_must_match_and_only_the_signed_in_password_changes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->put('/settings/password', ['current_password' => 'password', 'password' => 'UpdatedSecure123!', 'password_confirmation' => 'Mismatch'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $owner->fresh()->password));
        $this->put('/settings/password', ['user_id' => $other->id, 'current_password' => 'password', 'password' => 'UpdatedSecure123!', 'password_confirmation' => 'UpdatedSecure123!'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('UpdatedSecure123!', $owner->fresh()->password));
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
    }
}
