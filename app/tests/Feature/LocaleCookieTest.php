<?php

use Illuminate\Cookie\Middleware\EncryptCookies;

it('leaves the locale cookie unencrypted so the frontend switcher can write it', function () {
    expect(app(EncryptCookies::class)->isDisabled('locale'))->toBeTrue();
});
