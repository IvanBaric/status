<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('status_history');
    }
};
