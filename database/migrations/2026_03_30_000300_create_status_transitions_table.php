<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->index(['from_status_id', 'is_active']);
            $table->index(['to_status_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transitions');
    }
};
