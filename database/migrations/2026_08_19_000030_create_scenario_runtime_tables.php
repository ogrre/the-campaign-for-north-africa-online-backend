<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_initializations', function (Blueprint $table): void {
            $table->uuid('game_id')->primary();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreignUuid('scenario_definition_id')->constrained();
            $table->string('dataset_version');
            $table->timestamp('initialized_at');
        });

        Schema::create('game_scenario_states', function (Blueprint $table): void {
            $table->foreignUuid('game_id')->primary()->constrained()->cascadeOnDelete();
            $table->jsonb('initiative');
            $table->timestamps();
        });

        Schema::create('game_formations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->string('formation_definition_id');
            $table->foreign('formation_definition_id')->references('id')->on('formation_definitions');
            $table->timestamps();
            $table->unique(['game_id', 'formation_definition_id']);
        });

        Schema::create('game_units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->string('unit_definition_id');
            $table->foreignUuid('game_formation_id')->nullable()->constrained('game_formations')->nullOnDelete();
            $table->string('side');
            $table->foreign('unit_definition_id')->references('id')->on('unit_definitions');
            $table->timestamps();
            $table->unique(['game_id', 'unit_definition_id']);
        });

        Schema::create('game_unit_states', function (Blueprint $table): void {
            $table->uuid('game_unit_id')->primary();
            $table->foreign('game_unit_id')->references('id')->on('game_units')->cascadeOnDelete();
            $table->string('location_type');
            $table->string('location_id');
            $table->jsonb('statuses');
            $table->integer('cohesion')->nullable();
            $table->integer('morale')->nullable();
            $table->integer('cpa_spent')->default(0);
            $table->timestamps();
        });

        Schema::create('game_unit_toe', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('game_unit_id')->constrained()->cascadeOnDelete();
            $table->string('equipment_definition_id')->nullable();
            $table->decimal('strength_points', 8, 2);
            $table->string('state')->default('AVAILABLE');
            $table->foreign('equipment_definition_id')->references('id')->on('equipment_definitions');
            $table->timestamps();
        });

        Schema::create('game_unit_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('game_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_formation_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
        });

        Schema::create('game_unit_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('game_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attached_to_game_unit_id')->constrained('game_units')->cascadeOnDelete();
            $table->timestamp('attached_at');
            $table->timestamp('ended_at')->nullable();
        });

        foreach (['truck_pools', 'supply_stocks', 'aircraft_pools', 'pilot_pools', 'sgsu_pools', 'facilities', 'constructions', 'equipment_states'] as $component) {
            Schema::create("game_{$component}", function (Blueprint $table) use ($component): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
                $table->string('canonical_key');
                $table->jsonb('state');
                $table->timestamps();
                $table->unique(['game_id', 'canonical_key'], "game_{$component}_canonical_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (['equipment_states', 'constructions', 'facilities', 'sgsu_pools', 'pilot_pools', 'aircraft_pools', 'supply_stocks', 'truck_pools'] as $component) {
            Schema::dropIfExists("game_{$component}");
        }
        Schema::dropIfExists('game_unit_attachments');
        Schema::dropIfExists('game_unit_assignments');
        Schema::dropIfExists('game_unit_toe');
        Schema::dropIfExists('game_unit_states');
        Schema::dropIfExists('game_units');
        Schema::dropIfExists('game_formations');
        Schema::dropIfExists('game_scenario_states');
        Schema::dropIfExists('scenario_initializations');
    }
};
