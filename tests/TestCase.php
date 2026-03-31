<?php

declare(strict_types=1);

namespace IvanBaric\Status\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Status\Models\Status;
use IvanBaric\Status\Models\StatusHistory;
use IvanBaric\Status\Models\StatusTransition;
use IvanBaric\Status\Tests\Fixtures\Models\Order;
use IvanBaric\Status\Tests\Fixtures\Models\Post;
use Tests\TestCase as LaravelTestCase;

abstract class TestCase extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
        ]);

        Status::clearBootedModels();
        StatusHistory::clearBootedModels();
        StatusTransition::clearBootedModels();
        Order::clearBootedModels();
        Post::clearBootedModels();

        $this->createBaseSchema();
    }

    protected function createBaseSchema(): void
    {
        Schema::dropIfExists('status_transitions');
        Schema::dropIfExists('status_history');
        Schema::dropIfExists('statusables');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->timestamps();
        });

        Schema::create('statuses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('key');
            $table->string('name');
            $table->string('tooltip')->nullable();
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['type', 'key']);
            $table->index(['type', 'is_active']);
            $table->index(['type', 'sort_order']);
        });

        Schema::create('statusables', function (Blueprint $table): void {
            $table->id();
            $table->morphs('statusable');
            $table->foreignId('status_id')->constrained('statuses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['statusable_type', 'statusable_id', 'status_id']);
            $table->index('status_id');
        });

        Schema::create('status_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('statusable');
            $table->foreignId('from_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index('from_status_id');
            $table->index('to_status_id');
            $table->index('changed_by_user_id');
            $table->index('changed_at');
        });

        Schema::create('status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('from_status_id')->constrained('statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('statuses')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['from_status_id', 'to_status_id']);
        });
    }
}
