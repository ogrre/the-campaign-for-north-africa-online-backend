<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_review_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('map_section', 32);
            $table->string('region_id', 80)->nullable();
            $table->string('status', 24)->default('OPEN');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['map_section', 'region_id']);
        });

        Schema::create('map_review_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('session_id')->nullable();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('map_section', 32);
            $table->string('region_id', 80)->nullable();
            $table->string('entity_type', 24);
            $table->string('entity_id', 120);
            $table->string('decision', 24);
            $table->json('value')->nullable();
            $table->text('notes')->nullable();
            $table->json('source')->nullable();
            $table->timestamps();
            $table->foreign('session_id')->references('id')->on('map_review_sessions')->nullOnDelete();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['map_section', 'region_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_review_decisions');
        Schema::dropIfExists('map_review_sessions');
    }
};
