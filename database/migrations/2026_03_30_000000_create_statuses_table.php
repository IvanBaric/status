<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
