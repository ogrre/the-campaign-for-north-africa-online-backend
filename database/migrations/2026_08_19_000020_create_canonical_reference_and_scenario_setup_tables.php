<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('side');
            $table->string('nationality');
            $table->string('echelon');
            $table->string('parent_id')->nullable();
            $table->string('oa_chart')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
        });

        // Add the self-reference after the table (and its primary key) exists.
        // PostgreSQL rejects the inline self-FK during CREATE TABLE on some
        // schema-grammar versions used by the Docker image.
        Schema::table('formation_definitions', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('formation_definitions');
        });

        Schema::create('unit_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('designation');
            $table->string('display_name');
            $table->string('side');
            $table->string('nationality');
            $table->string('unit_type');
            $table->string('echelon')->nullable();
            $table->string('formation_definition_id')->nullable();
            $table->jsonb('vassal_gpids');
            $table->jsonb('images');
            $table->jsonb('provenance');
            $table->timestamps();
            $table->foreign('formation_definition_id')->references('id')->on('formation_definitions');
        });

        Schema::table('formation_definitions', function (Blueprint $table): void {
            $table->string('headquarters_unit_id')->nullable();
            $table->foreign('headquarters_unit_id')->references('id')->on('unit_definitions');
        });

        Schema::create('equipment_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('category');
            $table->jsonb('characteristics')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
        });

        Schema::create('unit_characteristic_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('nationality');
            $table->string('id_code');
            $table->integer('cpa');
            $table->string('maximum_toe')->nullable();
            $table->jsonb('values')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
        });

        Schema::create('scenario_setup_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('scenario_definition_id')->constrained()->cascadeOnDelete();
            $table->string('unit_definition_id');
            $table->string('formation_definition_id')->nullable();
            $table->string('side');
            $table->string('location_type');
            $table->string('location_id');
            $table->string('representation');
            $table->jsonb('initial_statuses');
            $table->jsonb('toe');
            $table->jsonb('provenance');
            $table->timestamps();
            $table->foreign('unit_definition_id')->references('id')->on('unit_definitions');
            $table->foreign('formation_definition_id')->references('id')->on('formation_definitions');
            $table->unique(['scenario_definition_id', 'unit_definition_id']);
        });

        foreach (['attachments', 'trucks', 'supplies', 'aircraft_pools', 'pilots', 'sgsu', 'facilities', 'constructions', 'equipment_states'] as $component) {
            Schema::create("scenario_setup_{$component}", function (Blueprint $table) use ($component): void {
                $table->id();
                $table->foreignUuid('scenario_definition_id')->constrained()->cascadeOnDelete();
                $table->string('canonical_key');
                $table->jsonb('data');
                $table->jsonb('provenance');
                $table->timestamps();
                $table->unique(['scenario_definition_id', 'canonical_key'], "scenario_{$component}_canonical_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (['equipment_states', 'constructions', 'facilities', 'sgsu', 'pilots', 'aircraft_pools', 'supplies', 'trucks', 'attachments'] as $component) {
            Schema::dropIfExists("scenario_setup_{$component}");
        }
        Schema::dropIfExists('scenario_setup_units');
        Schema::dropIfExists('unit_characteristic_definitions');
        Schema::dropIfExists('equipment_definitions');
        Schema::table('formation_definitions', function (Blueprint $table): void {
            $table->dropForeign(['headquarters_unit_id']);
            $table->dropColumn('headquarters_unit_id');
        });
        Schema::dropIfExists('unit_definitions');
        Schema::dropIfExists('formation_definitions');
    }
};
