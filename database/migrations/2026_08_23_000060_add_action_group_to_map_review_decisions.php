<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_review_decisions', function (Blueprint $table): void {
            $table->uuid('action_group_id')->nullable()->index()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('map_review_decisions', function (Blueprint $table): void {
            $table->dropColumn('action_group_id');
        });
    }
};
