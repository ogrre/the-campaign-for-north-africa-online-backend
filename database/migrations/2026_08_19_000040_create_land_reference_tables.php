<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_characteristic_definitions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->jsonb('values');
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('unit_toe_definitions', function (Blueprint $table): void {
            $table->string('unit_definition_id')->primary();
            $table->string('characteristic_definition_id')->nullable();
            $table->string('status');
            $table->jsonb('composition');
            $table->jsonb('provenance');
            $table->timestamps();
            $table->foreign('unit_definition_id')->references('id')->on('unit_definitions')->cascadeOnDelete();
            $table->foreign('characteristic_definition_id')->references('id')->on('unit_characteristic_definitions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_toe_definitions');
        Schema::dropIfExists('truck_characteristic_definitions');
    }
};
