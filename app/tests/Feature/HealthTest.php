<?php

it('serves the health endpoint', function () {
    $this->get('/up')->assertOk();
});
