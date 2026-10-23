<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_kit_profile_and_appearance_routes_are_replaced_by_core(): void
    {
        $this->assertFalse(Route::has('profile.edit'));
        $this->assertFalse(Route::has('profile.update'));
        $this->assertFalse(Route::has('appearance.edit'));
        $this->assertTrue(Route::has('profile.destroy'));
    }

    public function test_settings_pages_are_served_by_core(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings')->assertRedirect(route('settings.profile'));
        $this->actingAs($user)->get(route('settings.profile'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core::settings/profile')
                ->where('must_verify_email', true)
                ->where('profile.email_verified', true));
        $this->actingAs($user)->get(route('settings.privacy'))->assertOk();
        $this->actingAs($user)->get(route('settings.notifications'))->assertOk();
        $this->actingAs($user)->get(route('settings.appearance'))->assertOk();
    }

    public function test_profile_update_sends_the_verification_mail_for_the_app_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.profile.update'), [
                'name' => 'Test User',
                'email' => '  Test@Example.COM ',
                'timezone' => 'UTC',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.profile'));

        $user->refresh();

        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        // Пользователи ядра удаляются мягко (SoftDeletes).
        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'user.deleted_own_account',
            'causer_type' => 'user',
            'causer_id' => $user->id,
        ]);
    }

    public function test_last_creator_cannot_delete_their_account()
    {
        $this->seed(RolesSeeder::class);
        $creator = User::factory()->create()->assignRole('creator');

        $response = $this
            ->actingAs($creator)
            ->from(route('security.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('security.edit'));

        $this->assertAuthenticatedAs($creator);
        $this->assertNotSoftDeleted($creator);
    }

    public function test_creator_can_delete_their_account_when_another_creator_exists()
    {
        $this->seed(RolesSeeder::class);
        $creator = User::factory()->create()->assignRole('creator');
        User::factory()->create()->assignRole('creator');

        $this->actingAs($creator)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertSoftDeleted($creator);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('security.edit'));

        $this->assertNotNull($user->fresh());
    }
}
