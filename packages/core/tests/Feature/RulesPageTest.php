<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Ui\Navigation;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

function setRules(string $markdown): void
{
    $general = app(GeneralSettings::class);
    $general->rules_markdown = $markdown;
    $general->save();
}

function rulesHtml(mixed $test): string
{
    $html = '';
    $test->get(route('rules'))->assertOk()->assertInertia(function (AssertableInertia $page) use (&$html) {
        $page->component('core::rules')->where('html', function ($value) use (&$html): bool {
            $html = (string) $value;

            return true;
        });
    });

    return $html;
}

it('is public and renders markdown as html', function () {
    setRules("# Be nice\n\n- no spam\n- **no ads**");

    $html = rulesHtml($this);

    expect($html)->toContain('<h1>Be nice</h1>')->toContain('<li>no spam</li>')->toContain('<strong>no ads</strong>');
});

it('neutralises scripts, event handlers and javascript links', function () {
    setRules("<script>alert(1)</script>\n\n<img src=x onerror=\"alert(2)\">\n\n[click](javascript:alert(3))\n\n<a href=\"javascript:alert(4)\" onclick=\"x()\">raw</a>");

    $html = strtolower(rulesHtml($this));

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('<img')
        ->and($html)->not->toContain('onerror')
        ->and($html)->not->toContain('onclick')
        ->and($html)->not->toContain('javascript:');
});

it('passes null html for empty rules so the page can show an empty state', function () {
    setRules('   ');

    $this->get(route('rules'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::rules')->where('html', null));
});

it('resolves the nav rules item by route and the Welcome rules_url to the route url', function () {
    $items = array_column(app(Navigation::class)->items(null, request()), 'url', 'key');

    expect($items['rules'])->toBe(route('rules'));

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('layout.widgets.main.0.props.rules_url', route('rules')));
});

it('neutralises data:, vbscript: and autolink vectors', function () {
    setRules("[a](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)\n\n[b](vbscript:msgbox(1))\n\n<javascript:alert(5)>\n\n<data:text/html,x>\n\n[c](JaVaScRiPt:alert(6))");

    $html = strtolower(rulesHtml($this));

    expect($html)->not->toMatch('/href\s*=\s*["\']?\s*(javascript|vbscript|data):/')
        ->and($html)->not->toContain('<script');
});
