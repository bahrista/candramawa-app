<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superadmin()->create(['username' => 'boss']);
    }

    public function test_login_with_username_is_case_insensitive_and_redirects_home(): void
    {
        User::factory()->create(['username' => 'ani', 'password' => 'secret123']);

        $this->post('/login', ['username' => 'ANI', 'password' => 'secret123'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['username' => 'ani', 'password' => 'secret123']);

        $this->post('/login', ['username' => 'ani', 'password' => 'secret123'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_and_user_cannot_manage_users(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
    }

    public function test_superadmin_creates_user_with_temporary_password_and_forced_role_safety(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi', 'username' => 'Budi.S', 'email' => '', 'role' => 'user', 'password' => 'temp12345',
        ])->assertRedirect('/users');

        $u = User::where('username', 'budi.s')->first();
        $this->assertNotNull($u);
        $this->assertNull($u->email);
        $this->assertTrue($u->must_change_password);
        $this->assertSame($admin->id, $u->created_by);
        $this->assertSame('user', $u->role);
    }

    public function test_username_and_password_rules(): void
    {
        $this->actingAs($this->admin())->post('/users', [
            'name' => 'X', 'username' => 'a b', 'role' => 'user', 'password' => 'short',
        ])->assertSessionHasErrors(['username', 'password']);

        $this->post('/users', [
            'name' => 'X', 'username' => 'x', 'role' => 'user', 'password' => 'longenough',
        ])->assertSessionHasErrors('username');
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $u = new User(['name' => 'a', 'username' => 'a1', 'password' => 'x', 'role' => 'superadmin']);
        $this->assertSame(null, $u->role);
    }

    public function test_user_with_temporary_password_is_limited_to_profile_until_changed(): void
    {
        $u = User::factory()->mustChangePassword()->create(['password' => 'temp12345']);

        $this->actingAs($u)->get('/')->assertRedirect('/profile');
        $this->get('/profile')->assertOk();

        $this->put('/profile/password', [
            'current_password' => 'temp12345', 'password' => 'brandnew99', 'password_confirmation' => 'brandnew99',
        ])->assertRedirect('/');

        $this->assertFalse($u->fresh()->must_change_password);
        $this->get('/')->assertOk();
    }

    public function test_user_edits_own_name_and_email_but_not_username(): void
    {
        $u = User::factory()->create(['username' => 'ani']);

        $this->actingAs($u)->put('/profile', ['name' => 'Ani B', 'email' => 'ani@example.com', 'username' => 'hacked']);

        $u->refresh();
        $this->assertSame('Ani B', $u->name);
        $this->assertSame('ani', $u->username);
    }

    public function test_deactivate_ends_sessions_and_blocks_self_and_last_superadmin(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 's1', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->patch("/users/{$target->id}/active")->assertSessionHas('success');
        $this->assertFalse($target->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 's1']);

        $this->patch("/users/{$admin->id}/active")->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->is_active);

        $other = User::factory()->superadmin()->create();
        $this->actingAs($other)->patch("/users/{$admin->id}/active")->assertSessionHas('success');
        $this->patch("/users/{$other->id}/active")->assertSessionHasErrors('user'); // self
    }

    public function test_cannot_demote_or_deactivate_last_active_superadmin(): void
    {
        $admin = $this->admin();
        $second = User::factory()->superadmin()->inactive()->create();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => 'Boss', 'username' => 'boss', 'role' => 'user',
        ])->assertSessionHasErrors('role');

        $this->patch("/users/{$second->id}/active")->assertSessionHas('success'); // reactivate
        $this->put("/users/{$admin->id}", ['name' => 'Boss', 'username' => 'boss', 'role' => 'user'])
            ->assertSessionHasNoErrors();
    }

    public function test_setting_password_for_user_forces_change_and_ends_sessions(): void
    {
        $target = User::factory()->create();
        DB::table('sessions')->insert(['id' => 's2', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()]);

        $this->actingAs($this->admin())->put("/users/{$target->id}/password", ['password' => 'newtemp123'])
            ->assertSessionHas('success');

        $this->assertTrue($target->fresh()->must_change_password);
        $this->assertDatabaseMissing('sessions', ['id' => 's2']);
    }

    public function test_deactivated_while_logged_in_is_logged_out(): void
    {
        $u = User::factory()->inactive()->create();

        $this->actingAs($u)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_create_superadmin_command(): void
    {
        $this->artisan('app:create-superadmin', ['--name' => 'Bahrista', '--username' => 'Bahrista'])
            ->expectsQuestion('Password (min 8 characters)', 'longpassword')
            ->expectsQuestion('Confirm password', 'longpassword')
            ->assertSuccessful();

        $u = User::where('username', 'bahrista')->first();
        $this->assertTrue($u->isSuperadmin());
        $this->assertNull($u->created_by);
    }

    public function test_profile_saves_job_title_and_normalises_whatsapp(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->put('/profile', [
            'name' => 'Ani', 'email' => '', 'job_title' => ' Strategist ', 'whatsapp' => '0812-3456-7890',
        ])->assertSessionHasNoErrors();

        $u->refresh();
        $this->assertSame('Strategist', $u->job_title);
        $this->assertSame('+6281234567890', $u->whatsapp);

        $this->put('/profile', ['name' => 'Ani', 'whatsapp' => 'abc'])->assertSessionHasErrors('whatsapp');
        $this->put('/profile', ['name' => 'Ani', 'whatsapp' => '', 'job_title' => ''])->assertSessionHasNoErrors();
        $this->assertNull($u->fresh()->whatsapp);
        $this->assertNull($u->fresh()->job_title);
    }

    public function test_avatar_upload_replace_serve_and_remove(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();

        $this->actingAs($u)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg', 200, 200)])
            ->assertSessionHasNoErrors();
        $first = $u->fresh()->avatar_path;
        Storage::disk('local')->assertExists($first);
        $this->get("/avatars/{$u->id}")->assertOk();

        $this->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('b.png', 100, 100)]);
        Storage::disk('local')->assertMissing($first);

        $this->delete('/profile/avatar');
        $this->assertNull($u->fresh()->avatar_path);
        $this->get("/avatars/{$u->id}")->assertNotFound();
    }

    public function test_avatar_rejects_non_images_and_big_files_and_needs_login(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();

        $this->actingAs($u)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
        $this->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertSessionHasErrors('avatar');

        auth()->logout();
        $this->get("/avatars/{$u->id}")->assertRedirect('/login');
    }

    public function test_user_with_temporary_password_can_still_load_avatar(): void
    {
        Storage::fake('local');
        $u = User::factory()->mustChangePassword()->create();
        $this->actingAs($u)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')]);

        $this->get("/avatars/{$u->id}")->assertOk();
    }

    public function test_logout_other_devices_keeps_current_session_and_needs_password(): void
    {
        $u = User::factory()->create(['password' => 'secret123']);
        DB::table('sessions')->insert(['id' => 'other1', 'user_id' => $u->id, 'payload' => 'x', 'last_activity' => time()]);

        $this->actingAs($u)->post('/profile/sessions/others', ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('sessions', ['id' => 'other1']);

        $this->post('/profile/sessions/others', ['current_password' => 'secret123'])->assertSessionHas('success');
        $this->assertDatabaseMissing('sessions', ['id' => 'other1']);
    }

    public function test_repeated_wrong_passwords_lock_that_username_for_a_while_with_a_clear_message(): void
    {
        User::factory()->create(['username' => 'ani', 'password' => 'secret123']);
        User::factory()->create(['username' => 'budi', 'password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'ani', 'password' => 'wrong'])->assertSessionHasErrors('username');
        }

        // even the right password is refused now, and the message says why
        $this->post('/login', ['username' => 'ani', 'password' => 'secret123'])->assertSessionHasErrors('username');
        $this->assertStringStartsWith('Too many login attempts', session('errors')->first('username'));
        $this->assertGuest();

        // ... but a colleague on the same IP address is not affected
        $this->post('/login', ['username' => 'budi', 'password' => 'secret123'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_a_successful_login_resets_the_failed_attempt_count(): void
    {
        User::factory()->create(['username' => 'ani', 'password' => 'secret123']);

        for ($round = 0; $round < 3; $round++) {
            for ($i = 0; $i < 4; $i++) {
                $this->post('/login', ['username' => 'ani', 'password' => 'wrong']);
            }
            $this->post('/login', ['username' => 'ani', 'password' => 'secret123'])->assertRedirect('/');
            auth()->logout();
        }

        $this->assertGuest();
    }
}
