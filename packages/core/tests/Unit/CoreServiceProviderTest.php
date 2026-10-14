<?php

it('publishes the core config', function () {
    expect(config('core.version'))->toBe('0.1.0');
});
