<?php

declare(strict_types=1);

use Inertia\ComponentNotFoundException;
use Inertia\Inertia;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
    config()->set('inertia.pages.ensure_pages_exist', true);
});

it('finds module pages by namespaced name', function () {
    expect(app('inertia.view-finder')->find('dummy::Index'))
        ->toEndWith('Fixtures/DummyModule/resources/js/pages/Index.tsx');
});

it('throws a clear error for a missing module page', function () {
    expect(fn () => Inertia::render('dummy::Missing')->toResponse(request()))
        ->toThrow(ComponentNotFoundException::class);
});
