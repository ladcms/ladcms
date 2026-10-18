<?php

use Inertia\Testing\AssertableInertia;

it('renders the core welcome page', function () {
    $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::welcome')
        ->has('version'));
});

it('shares the three core navigation items with the public layout', function () {
    $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('layout.navigation', 3)
        ->where('layout.navigation.0.key', 'home')
        ->where('layout.navigation.0.active', true)
        ->where('layout.navigation.1.key', 'users')
        ->where('layout.navigation.2.key', 'rules')
        ->has('layout.site.name')
        ->has('i18n'));
});
