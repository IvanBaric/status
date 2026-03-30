<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use IvanBaric\Status\Models\Status;
use IvanBaric\Status\Tests\Fixtures\Models\Order;
use IvanBaric\Status\Tests\Fixtures\Models\Post;

it('assigns a current status and records transition history', function (): void {
    $draft = makeStatus([
        'type' => 'blog',
        'key' => 'draft',
        'name' => 'Draft',
        'sort_order' => 1,
        'is_default' => true,
    ]);

    $published = makeStatus([
        'type' => 'blog',
        'key' => 'published',
        'name' => 'Published',
        'sort_order' => 2,
    ]);

    $post = Post::query()->create(['title' => 'Status test']);

    $post->setStatus('draft');
    $post->setStatus($published, reason: 'Ready', source: 'manual', meta: ['approved' => true]);

    expect($post->getStatus()?->is($published))->toBeTrue()
        ->and($post->getStatusKey())->toBe('published')
        ->and($post->getStatusName())->toBe('Published')
        ->and($post->hasStatus($draft))->toBeFalse()
        ->and($post->hasStatus('published'))->toBeTrue()
        ->and($post->statuses()->count())->toBe(1)
        ->and($post->statusHistory()->count())->toBe(2)
        ->and($post->statusHistory()->first()?->from_status_key)->toBe('draft')
        ->and($post->statusHistory()->first()?->to_status_key)->toBe('published');
});

it('clears a status and stores a history snapshot', function (): void {
    makeStatus([
        'type' => 'blog',
        'key' => 'draft',
        'name' => 'Draft',
        'sort_order' => 1,
    ]);

    $post = Post::query()->create(['title' => 'Clear test']);

    $post->setStatus('draft');
    $post->clearStatus(reason: 'Manual clear', source: 'manual');

    expect($post->getStatus())->toBeNull()
        ->and($post->statuses()->count())->toBe(0)
        ->and($post->statusHistory()->count())->toBe(2)
        ->and($post->statusHistory()->first()?->from_status_key)->toBe('draft')
        ->and($post->statusHistory()->first()?->to_status_key)->toBeNull();
});

it('prevents changing or clearing a final status', function (): void {
    makeStatus([
        'type' => 'blog',
        'key' => 'published',
        'name' => 'Published',
        'sort_order' => 1,
        'is_final' => true,
    ]);

    makeStatus([
        'type' => 'blog',
        'key' => 'archived',
        'name' => 'Archived',
        'sort_order' => 2,
    ]);

    $post = Post::query()->create(['title' => 'Final test']);
    $post->setStatus('published');

    expect(fn () => $post->setStatus('archived'))->toThrow(DomainException::class)
        ->and(fn () => $post->clearStatus())->toThrow(DomainException::class);
});

it('invalidates cached lookups when a status changes activity or type', function (): void {
    Cache::flush();

    $status = makeStatus([
        'type' => 'blog',
        'key' => 'draft',
        'name' => 'Draft',
        'sort_order' => 1,
    ]);

    expect(Status::keysFor('blog'))->toBe(['draft'])
        ->and(Status::keysFor('news'))->toBe([]);

    $status->update([
        'type' => 'news',
        'is_active' => false,
    ]);

    expect(Status::keysFor('blog'))->toBe([])
        ->and(Status::keysFor('news'))->toBe([]);
});

it('requires a persisted model for status operations', function (): void {
    makeStatus([
        'type' => 'blog',
        'key' => 'draft',
        'name' => 'Draft',
        'sort_order' => 1,
    ]);

    $post = new Post(['title' => 'Unsaved']);

    expect(fn () => $post->setStatus('draft'))->toThrow(LogicException::class)
        ->and(fn () => $post->clearStatus())->toThrow(LogicException::class);
});

it('infers the default status type from the model class name', function (): void {
    makeStatus([
        'type' => 'order',
        'key' => 'pending',
        'name' => 'Pending',
        'sort_order' => 1,
    ]);

    $order = Order::query()->create(['number' => 'ORD-1']);
    $order->setStatus('pending');

    expect($order->statusType())->toBe('order')
        ->and($order->getStatusKey())->toBe('pending')
        ->and(Order::query()->whereHasStatus('pending')->count())->toBe(1);
});

it('can disable transition history recording via config', function (): void {
    config(['status.history.enabled' => false]);

    makeStatus([
        'type' => 'blog',
        'key' => 'draft',
        'name' => 'Draft',
        'sort_order' => 1,
    ]);

    makeStatus([
        'type' => 'blog',
        'key' => 'published',
        'name' => 'Published',
        'sort_order' => 2,
    ]);

    $post = Post::query()->create(['title' => 'No history']);

    $post->setStatus('draft');
    $post->setStatus('published');

    expect($post->getStatusKey())->toBe('published')
        ->and(fn () => $post->statusHistory()->count())->toThrow(LogicException::class);
});

function makeStatus(array $overrides = []): Status
{
    return Status::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'type' => 'blog',
        'key' => 'draft',
        'name' => 'Draft',
        'tooltip' => null,
        'color' => 'zinc',
        'icon' => 'pencil',
        'sort_order' => 0,
        'is_default' => false,
        'is_final' => false,
        'is_active' => true,
        'description' => null,
        'meta' => null,
    ], $overrides));
}
