<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('kind');
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('board_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('kind');
            $table->string('image')->nullable();
            $table->text('vassal_path')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('map_grid_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('section');
            $table->decimal('dx', 10, 3);
            $table->decimal('dy', 10, 3);
            $table->decimal('x0', 10, 3);
            $table->decimal('y0', 10, 3);
            $table->boolean('sideways');
            $table->boolean('snap_to');
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('map_zone_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('category');
            $table->jsonb('polygon');
            $table->text('vassal_path')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('terrain_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('hex_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('map_section');
            $table->unsignedSmallInteger('column');
            $table->unsignedSmallInteger('row');
            $table->jsonb('neighbors');
            $table->string('topology_status');
            $table->string('terrain_status');
            $table->string('terrain_id')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
            $table->index(['map_section', 'column', 'row']);
        });
        Schema::create('hexside_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('hex_a');
            $table->string('hex_b');
            $table->jsonb('features');
            $table->string('status');
            $table->jsonb('provenance');
            $table->timestamps();
            $table->unique(['hex_a', 'hex_b']);
        });
        Schema::create('network_edges', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('network_type');
            $table->string('hex_a');
            $table->string('hex_b');
            $table->jsonb('properties');
            $table->string('status');
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('location_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('kind');
            $table->string('map_section')->nullable();
            $table->string('hex_id')->nullable();
            $table->string('area_id')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('area_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('category');
            $table->jsonb('polygon')->nullable();
            $table->jsonb('provenance');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['area_definitions', 'location_definitions', 'network_edges', 'hexside_definitions', 'hex_definitions', 'terrain_definitions', 'map_zone_definitions', 'map_grid_definitions', 'board_definitions', 'map_definitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
