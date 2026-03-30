<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statusables') || ! Schema::hasColumn('statusables', 'status_type')) {
            return;
        }

        Schema::table('statusables', function (Blueprint $table): void {
            $table->dropUnique('statusables_unique_current_status');
            $table->dropIndex('statusables_status_type_status_id_index');
            $table->dropColumn('status_type');
        });

        Schema::table('statusables', function (Blueprint $table): void {
            $table->unique(
                ['statusable_type', 'statusable_id'],
                'statusables_unique_current_status'
            );
            $table->index('status_id', 'statusables_status_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('statusables') || Schema::hasColumn('statusables', 'status_type')) {
            return;
        }

        Schema::table('statusables', function (Blueprint $table): void {
            $table->dropUnique('statusables_unique_current_status');
            $table->dropIndex('statusables_status_id_index');

            if (! Schema::hasColumn('statusables', 'status_type')) {
                $table->string('status_type')->default('');
            }
        });

        Schema::table('statusables', function (Blueprint $table): void {
            $table->unique(
                ['statusable_type', 'statusable_id', 'status_type'],
                'statusables_unique_current_status'
            );
            $table->index(['status_type', 'status_id'], 'statusables_status_type_status_id_index');
        });
    }
};
