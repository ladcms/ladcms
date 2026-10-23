<?php

namespace Tests\Feature;

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
}
