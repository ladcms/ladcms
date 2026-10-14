<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\UserMentioned;
use Ladcms\Core\Services\MentionService;

uses(RefreshDatabase::class);

it('extracts existing mentioned users without duplicates', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $html = '<p>Hi <span data-mention data-user-id="'.$a->id.'">@a</span>, '
        .'<span class="mention" data-user-id="'.$b->id.'" data-mention="">@b</span> '
        .'<span data-mention data-user-id="999999">@ghost</span> '
        .'<span data-mention data-user-id="'.$a->id.'">@a again</span> '
        .'<span data-user-id="'.$b->id.'">not a mention</span></p>';

    $users = app(MentionService::class)->extract($html);

    expect($users)->toHaveCount(2)
        ->and($users->pluck('id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('returns an empty collection when there are no mentions', function () {
    expect(app(MentionService::class)->extract('<p>plain text</p>'))->toBeEmpty();
});

it('notifies mentioned users except the author', function () {
    Notification::fake();

    $author = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $context = User::factory()->create();

    $html = '<span data-mention data-user-id="'.$author->id.'">@me</span>'
        .'<span data-mention data-user-id="'.$a->id.'">@a</span>'
        .'<span data-mention data-user-id="'.$b->id.'">@b</span>';

    $count = app(MentionService::class)->notify($html, $author, $context, '/posts/1');

    expect($count)->toBe(2);
    Notification::assertSentTo([$a, $b], UserMentioned::class, function (UserMentioned $n, array $channels, User $notifiable) use ($author, $context) {
        $data = $n->toDatabase($notifiable);

        return $channels === ['database']
            && $data['author_id'] === $author->id
            && $data['author_login'] === $author->login
            && $data['context_type'] === $context->getMorphClass()
            && $data['context_id'] === $context->getKey()
            && $data['url'] === '/posts/1';
    });
    Notification::assertNotSentTo($author, UserMentioned::class);
});
