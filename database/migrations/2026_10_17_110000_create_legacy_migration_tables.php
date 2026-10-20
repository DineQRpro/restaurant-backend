<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50);
            $table->string('mode', 20);
            $table->string('status', 20);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['group', 'status']);
        });

        Schema::create('legacy_migration_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->string('destination_table');
            $table->string('decision_key');
            $table->string('state', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'destination_table', 'decision_key'], 'legacy_migration_mappings_unique');
        });

        Schema::create('legacy_migration_id_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('legacy_migration_runs')->cascadeOnDelete();
            $table->string('source_table');
            $table->string('source_id');
            $table->string('destination_table');
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'source_id'], 'legacy_migration_id_maps_source_unique');
            $table->index(['destination_table', 'destination_id'], 'legacy_migration_id_maps_destination_index');
        });

        Schema::create('legacy_migration_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('legacy_migration_runs')->cascadeOnDelete();
            $table->string('migrator');
            $table->string('source_table');
            $table->unsignedBigInteger('last_source_id')->default(0);
            $table->unsignedInteger('batch_size')->default(100);
            $table->timestamps();

            $table->unique(['run_id', 'migrator'], 'legacy_migration_checkpoints_unique');
        });

        Schema::create('legacy_migration_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('legacy_migration_runs')->cascadeOnDelete();
            $table->string('source_table');
            $table->string('source_id')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('status', 20)->default('failed');
            $table->timestamps();

            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_errors');
        Schema::dropIfExists('legacy_migration_checkpoints');
        Schema::dropIfExists('legacy_migration_id_maps');
        Schema::dropIfExists('legacy_migration_mappings');
        Schema::dropIfExists('legacy_migration_runs');
    }
};
