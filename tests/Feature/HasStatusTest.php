<?php

declare(strict_types=1);

namespace IvanBaric\Status\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use IvanBaric\Status\Events\StatusChanged;
use IvanBaric\Status\Models\Status;
use IvanBaric\Status\Models\StatusTransition;
use IvanBaric\Status\Tests\Fixtures\Models\Order;
use IvanBaric\Status\Tests\Fixtures\Models\Post;
use IvanBaric\Status\Tests\TestCase;

class HasStatusTest extends TestCase
{
    public function test_assigns_a_current_status_and_records_transition_history(): void
    {
        Event::fake();

        $draft = $this->makeStatus([
            'type' => 'blog',
            'key' => 'draft',
            'name' => 'Draft',
            'sort_order' => 1,
            'is_default' => true,
        ]);

        $published = $this->makeStatus([
            'type' => 'blog',
            'key' => 'published',
            'name' => 'Published',
            'sort_order' => 2,
        ]);

        $this->makeTransition($draft, $published);

        $post = Post::query()->create(['title' => 'Status test']);

        $first = $post->trySetStatus('draft');
        $second = $post->trySetStatus($published, reason: 'Ready', meta: ['approved' => true]);

        $this->assertTrue($first->allowed);
        $this->assertTrue($second->allowed);
        $this->assertTrue($post->getStatus()?->is($published));
        $this->assertSame('published', $post->getStatusKey());
        $this->assertSame('Published', $post->getStatusName());
        $this->assertTrue($post->hasStatus('published'));
        $this->assertSame(1, $post->statuses()->count());
        $this->assertSame(2, $post->statusHistory()->count());
        $this->assertSame('draft', $post->statusHistory()->first()?->fromStatus?->key);
        $this->assertSame('published', $post->statusHistory()->first()?->toStatus?->key);

        Event::assertDispatched(StatusChanged::class, 2);
    }

    public function test_returns_denial_results_for_same_status_and_final_status_rules(): void
    {
        $published = $this->makeStatus([
            'type' => 'blog',
            'key' => 'published',
            'name' => 'Published',
            'sort_order' => 1,
            'is_default' => true,
            'is_final' => true,
        ]);

        $archived = $this->makeStatus([
            'type' => 'blog',
            'key' => 'archived',
            'name' => 'Archived',
            'sort_order' => 2,
        ]);

        $this->makeTransition($published, $archived);

        $post = Post::query()->create(['title' => 'Final test']);

        $this->assertTrue($post->trySetStatus('published')->allowed);

        $same = $post->inspectStatusTransition('published');
        $change = $post->trySetStatus('archived');
        $clear = $post->clearStatus();

        $this->assertFalse($same->allowed);
        $this->assertFalse($change->allowed);
        $this->assertFalse($clear->allowed);
    }

    public function test_enforces_transition_rules_and_reports_allowed_statuses(): void
    {
        $draft = $this->makeStatus(['type' => 'blog', 'key' => 'draft', 'name' => 'Draft', 'is_default' => true]);
        $review = $this->makeStatus(['type' => 'blog', 'key' => 'review', 'name' => 'Review']);
        $published = $this->makeStatus(['type' => 'blog', 'key' => 'published', 'name' => 'Published']);

        $this->makeTransition($draft, $review);
        $this->makeTransition($review, $published);

        $post = Post::query()->create(['title' => 'Transition test']);

        $this->assertSame(['draft'], $post->allowedStatuses()->pluck('key')->all());

        $post->trySetStatus('draft');

        $this->assertSame(1, $post->allowedTransitions()->count());
        $this->assertSame(['review'], $post->allowedStatuses()->pluck('key')->all());
        $this->assertFalse($post->trySetStatus('published')->allowed);
        $this->assertTrue($post->trySetStatus('review')->allowed);
    }

    public function test_invalidates_cached_lookups_when_a_status_changes_activity_or_type(): void
    {
        Cache::flush();

        $status = $this->makeStatus([
            'type' => 'blog',
            'key' => 'draft',
            'name' => 'Draft',
            'sort_order' => 1,
        ]);

        $this->assertSame(['draft'], Status::keysFor('blog'));
        $this->assertSame([], Status::keysFor('news'));

        $status->update([
            'type' => 'news',
            'is_active' => false,
        ]);

        $this->assertSame([], Status::keysFor('blog'));
        $this->assertSame([], Status::keysFor('news'));
    }

    public function test_returns_denial_results_for_unsaved_models(): void
    {
        $this->makeStatus([
            'type' => 'blog',
            'key' => 'draft',
            'name' => 'Draft',
            'sort_order' => 1,
            'is_default' => true,
        ]);

        $post = new Post(['title' => 'Unsaved']);

        $this->assertFalse($post->trySetStatus('draft')->allowed);
        $this->assertFalse($post->clearStatus()->allowed);
    }

    public function test_uses_the_model_status_type_for_order_statuses(): void
    {
        $this->makeStatus([
            'type' => 'order',
            'key' => 'pending',
            'name' => 'Pending',
            'sort_order' => 1,
            'is_default' => true,
        ]);

        $order = Order::query()->create(['number' => 'ORD-1']);
        $order->trySetStatus('pending');

        $this->assertSame('pending', $order->getStatusKey());
        $this->assertSame(1, Order::query()->whereHasStatus('pending')->count());
    }

    public function test_can_disable_history_recording_via_config(): void
    {
        config(['status.history_enabled' => false]);

        $draft = $this->makeStatus([
            'type' => 'blog',
            'key' => 'draft',
            'name' => 'Draft',
            'sort_order' => 1,
            'is_default' => true,
        ]);

        $published = $this->makeStatus([
            'type' => 'blog',
            'key' => 'published',
            'name' => 'Published',
            'sort_order' => 2,
        ]);

        $this->makeTransition($draft, $published);

        $post = Post::query()->create(['title' => 'No history']);

        $post->trySetStatus('draft');
        $post->trySetStatus('published');

        $this->assertSame('published', $post->getStatusKey());
        $this->assertSame(0, $post->statusHistory()->count());
    }

    protected function makeStatus(array $overrides = []): Status
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

    protected function makeTransition(Status $from, Status $to, array $overrides = []): StatusTransition
    {
        return StatusTransition::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'from_status_id' => $from->getKey(),
            'to_status_id' => $to->getKey(),
            'is_active' => true,
            'label' => null,
            'description' => null,
            'meta' => null,
        ], $overrides));
    }
}
