<?php

use Inertia\Testing\AssertableInertia;

it('renders the core welcome page', function () {
    $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::welcome')
        ->has('version'));
});
