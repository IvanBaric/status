<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statusables', function (Blueprint $table): void {
            $table->id();
            $table->morphs('statusable');
            $table->foreignId('status_id')->constrained('statuses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['statusable_type', 'statusable_id', 'status_id']);
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statusables');
    }
};
