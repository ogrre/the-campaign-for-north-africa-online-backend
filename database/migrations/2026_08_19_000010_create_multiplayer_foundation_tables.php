<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('initial_turn')->nullable();
            $table->unsignedTinyInteger('initial_operation_stage')->nullable();
            $table->string('source_document');
            $table->string('source_reference');
            $table->boolean('setup_complete')->default(false);
            $table->timestamps();
        });

        Schema::create('game_role_definitions', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->string('name');
            $table->boolean('required_to_start')->default(false);
            $table->timestamps();
        });

        Schema::create('games', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignId('creator_id')->constrained('users');
            $table->foreignUuid('scenario_definition_id')->constrained();
            $table->string('status')->default('lobby');
            $table->string('ruleset')->default('cna-2021-integrated-errata');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('event_sequence')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('game_teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
            $table->unique(['game_id', 'code']);
        });

        Schema::create('game_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('game_teams')->nullOnDelete();
            $table->string('privilege')->default('PLAYER');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
            $table->unique(['game_id', 'user_id']);
        });

        Schema::create('game_player_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('game_membership_id')->constrained()->cascadeOnDelete();
            $table->string('role_code');
            $table->foreign('role_code')->references('code')->on('game_role_definitions');
            $table->timestamps();
            $table->unique(['game_membership_id', 'role_code']);
        });

        Schema::create('game_turn_states', function (Blueprint $table): void {
            $table->uuid('game_id')->primary();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->unsignedSmallInteger('turn');
            $table->unsignedTinyInteger('operation_stage');
            $table->string('phase')->default('AWAITING_INITIAL_DECISIONS');
            $table->timestamps();
        });

        Schema::create('game_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->string('invited_email')->nullable();
            $table->string('team_code')->nullable();
            $table->string('privilege')->default('PLAYER');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pending_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('game_teams')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('PENDING');
            $table->string('required_role_code')->nullable();
            $table->foreign('required_role_code')->references('code')->on('game_role_definitions');
            $table->string('visibility')->default('PRIVATE_TEAM');
            $table->jsonb('public_context')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'status']);
        });

        Schema::create('decision_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('pending_decision_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_membership_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('WAITING');
            $table->timestamp('responded_at')->nullable();
            $table->unique(['pending_decision_id', 'game_membership_id']);
        });

        Schema::create('game_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('expected_revision')->nullable();
            $table->unsignedInteger('resulting_revision')->nullable();
            $table->string('status');
            $table->jsonb('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('game_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('type');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('game_teams')->nullOnDelete();
            $table->string('visibility')->default('PUBLIC');
            $table->jsonb('payload')->nullable();
            $table->timestamps();
            $table->unique(['game_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
        Schema::dropIfExists('game_actions');
        Schema::dropIfExists('decision_participants');
        Schema::dropIfExists('pending_decisions');
        Schema::dropIfExists('game_invitations');
        Schema::dropIfExists('game_turn_states');
        Schema::dropIfExists('game_player_roles');
        Schema::dropIfExists('game_memberships');
        Schema::dropIfExists('game_teams');
        Schema::dropIfExists('games');
        Schema::dropIfExists('game_role_definitions');
        Schema::dropIfExists('scenario_definitions');
    }
};
