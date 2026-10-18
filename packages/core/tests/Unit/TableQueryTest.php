<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Ladcms\Core\Admin\TableQuery;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

function tq(array $params, array $searchable = ['name']): Builder
{
    return TableQuery::apply(User::query(), Request::create('/x', 'GET', $params), ['login', 'name'], 'id', $searchable);
}

it('sorts by an allowed column', function () {
    User::factory()->create(['login' => 'bbb']);
    User::factory()->create(['login' => 'aaa']);

    expect(tq(['sort' => 'login', 'direction' => 'asc'])->pluck('login')->all())->toBe(['aaa', 'bbb']);
});

it('falls back to the default sort for a forbidden column', function () {
    $a = User::factory()->create(['login' => 'bbb']);
    $b = User::factory()->create(['login' => 'aaa']);

    expect(tq(['sort' => 'password', 'direction' => 'asc'])->pluck('id')->all())->toBe([$a->id, $b->id])
        ->and(tq(['sort' => 'password'])->toSql())->toContain('"id" desc');
});

it('falls back to the default direction when invalid', function () {
    expect(tq(['sort' => 'login', 'direction' => 'sideways'])->toSql())->toContain('"login" desc');
});

it('searches a literal percent sign', function () {
    User::factory()->create(['name' => 'ab%c']);
    User::factory()->create(['name' => 'abxc']);

    expect(tq(['q' => 'ab%'])->pluck('name')->all())->toBe(['ab%c']);
});

it('searches cyrillic', function () {
    User::factory()->create(['name' => 'Вадим']);
    User::factory()->create(['name' => 'Анна']);

    expect(tq(['q' => 'Вад'])->pluck('name')->all())->toBe(['Вадим']);
});

it('ignores search when no searchable columns', function () {
    User::factory()->count(2)->create();

    expect(tq(['q' => 'zzz'], [])->count())->toBe(2);
});

it('keeps the query string in pagination links', function () {
    User::factory()->count(3)->create();
    $request = Request::create('/x', 'GET', ['sort' => 'login', 'q' => 'a']);

    $page = TableQuery::paginate(TableQuery::apply(User::query(), $request, ['login'], 'id'), $request, 2);

    expect($page->nextPageUrl())->toContain('sort=login')->toContain('q=a')->toContain('page=2');
});
