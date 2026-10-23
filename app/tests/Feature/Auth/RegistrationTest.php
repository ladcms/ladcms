<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Settings\RegistrationSettings;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());

        $this->seed(RolesSeeder::class);
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'login' => 'Test_User',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));

        $user = User::query()->where('login', 'test_user')->firstOrFail();
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('127.0.0.1', $user->registered_ip);
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_login_is_required_to_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_registration_can_be_closed()
    {
        $settings = app(RegistrationSettings::class);
        $settings->open = false;
        $settings->save();

        $this->get(route('register'))->assertNotFound();

        $this->post(route('register.store'), [
            'login' => 'test_user',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['login' => 'test_user']);
    }

    public function test_registration_auto_verifies_when_email_verification_is_disabled()
    {
        $settings = app(RegistrationSettings::class);
        $settings->require_email_verification = false;
        $settings->save();

        $this->post(route('register.store'), [
            'login' => 'test_user',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertNotNull(User::query()->where('login', 'test_user')->sole()->email_verified_at);
        $this->get(route('home'))->assertOk();
    }

    public function test_invite_only_registration_is_closed_without_an_invite_validator()
    {
        $settings = app(RegistrationSettings::class);
        $settings->invite_only = true;
        $settings->save();

        $this->post(route('register.store'), [
            'login' => 'test_user',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite_code' => 'anything',
        ])->assertForbidden();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['login' => 'test_user']);
    }
}
