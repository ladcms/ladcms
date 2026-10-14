<?php

it('serves the health endpoint', function () {
    $this->get('/up')->assertOk();
});

it('renders the welcome page', function () {
    $this->get('/')->assertOk();
});
