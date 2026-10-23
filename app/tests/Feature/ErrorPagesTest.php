<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_renders_the_core_error_page_with_404(): void
    {
        $this->get('/definitely-not-here')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core::error', true)
                ->where('status', 404)
                ->has('title')
                ->has('message')
                ->has('layout.site.name'));
    }

    public function test_unknown_url_still_returns_json_for_json_requests(): void
    {
        $this->getJson('/definitely-not-here')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_unknown_profile_renders_the_error_page(): void
    {
        $this->get('/u/nobody')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('core::error'));
    }

    public function test_unmatched_url_keeps_the_signed_in_user(): void
    {
        $user = User::factory()->create(['language' => 'ru']);

        $this->actingAs($user)->get('/dashboard')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core::error')
                ->where('auth.user.id', $user->id)
                ->where('locale', 'ru'));
    }

    public function test_post_to_an_unknown_url_renders_the_405_error_page(): void
    {
        $this->post('/definitely-not-here')
            ->assertStatus(405)
            ->assertInertia(fn (Assert $page) => $page
                ->component('core::error', true)
                ->where('status', 405)
                ->has('layout.site.name'));
    }

    public function test_unknown_asset_like_url_returns_a_plain_404(): void
    {
        $response = $this->get('/favicon.ico');

        $response->assertNotFound();
        $this->assertNull($response->headers->get('X-Inertia'));
        $this->assertStringNotContainsString('data-page', (string) $response->getContent());
        $this->assertStringNotContainsString('core::error', (string) $response->getContent());
    }
}
