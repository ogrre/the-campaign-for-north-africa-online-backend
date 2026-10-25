<?php

namespace Tests\Feature;

use App\Application\Multiplayer\GameEventRecorder;
use App\Domain\Multiplayer\EventVisibility;
use App\Infrastructure\CnaData\CanonicalDataImporter;
use App\Models\Game;
use App\Models\GameInvitation;
use App\Models\GameMembership;
use App\Models\ScenarioDefinition;
use App\Models\User;
use Database\Seeders\MultiplayerReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiplayerFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MultiplayerReferenceSeeder::class);
        app(CanonicalDataImporter::class)->import();
    }

    public function test_creator_can_create_game_and_game_created_event_is_recorded(): void
    {
        [$user, $game] = $this->createGame();
        $this->assertSame($user->id, $game->creator_id);
        $this->assertDatabaseHas('game_events', ['game_id' => $game->id, 'sequence' => 1, 'type' => 'GameCreated']);
        $this->assertDatabaseHas('game_events', ['game_id' => $game->id, 'sequence' => 2, 'type' => 'PlayerJoinedGame']);
    }

    public function test_non_member_cannot_view_inaccessible_game(): void
    {
        [, $game] = $this->createGame();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/games/{$game->id}")->assertForbidden();
    }

    public function test_player_can_join_team_and_event_sequence_remains_stable(): void
    {
        [, $game] = $this->createGame();
        $player = User::factory()->create();
        Sanctum::actingAs($player);
        $this->postJson("/api/games/{$game->id}/join", ['team' => 'COMMONWEALTH', 'revision' => 1])->assertCreated();
        $this->assertDatabaseHas('game_memberships', ['game_id' => $game->id, 'user_id' => $player->id]);
        $this->assertSame([1, 2, 3], $game->events()->orderBy('sequence')->pluck('sequence')->all());
        $this->assertSame(2, $game->fresh()->revision);
    }

    public function test_player_can_have_multiple_roles(): void
    {
        [$creator, $game] = $this->createGame();
        $membership = $game->memberships()->where('user_id', $creator->id)->firstOrFail();
        $this->postJson("/api/games/{$game->id}/players/{$membership->id}/roles", ['role' => 'COMMANDER_IN_CHIEF', 'revision' => 1])->assertCreated();
        $this->postJson("/api/games/{$game->id}/players/{$membership->id}/roles", ['role' => 'FRONT_LINE_COMMANDER', 'revision' => 2])->assertCreated();
        $this->assertSame(['COMMANDER_IN_CHIEF', 'FRONT_LINE_COMMANDER'], $membership->roles()->orderBy('code')->pluck('code')->all());
    }

    public function test_game_starts_with_both_teams_and_commanders_then_lobby_is_locked(): void
    {
        [$creator, $game] = $this->createGame();
        $axis = $game->memberships()->firstOrFail();
        $this->postJson("/api/games/{$game->id}/players/{$axis->id}/roles", ['role' => 'COMMANDER_IN_CHIEF', 'revision' => 1])->assertCreated();

        $commonwealthUser = User::factory()->create();
        Sanctum::actingAs($commonwealthUser);
        $this->postJson("/api/games/{$game->id}/join", ['team' => 'COMMONWEALTH', 'revision' => 2])->assertCreated();
        $commonwealth = GameMembership::where('user_id', $commonwealthUser->id)->firstOrFail();

        Sanctum::actingAs($creator);
        $this->postJson("/api/games/{$game->id}/players/{$commonwealth->id}/roles", ['role' => 'COMMANDER_IN_CHIEF', 'revision' => 3])->assertCreated();
        $this->postJson("/api/games/{$game->id}/start", ['revision' => 4])->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('game_turn_states', ['game_id' => $game->id, 'turn' => 1, 'operation_stage' => 1]);
        $this->assertDatabaseCount('pending_decisions', 2);
        $this->assertDatabaseHas('game_events', ['game_id' => $game->id, 'type' => 'GameStarted']);
        $this->patchJson("/api/games/{$game->id}", ['name' => 'Too late', 'revision' => 5])->assertForbidden();
    }

    public function test_game_cannot_start_without_both_commanders(): void
    {
        [, $game] = $this->createGame();
        $this->postJson("/api/games/{$game->id}/start", ['revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('roles');
    }

    public function test_unauthorized_player_cannot_alter_lobby(): void
    {
        [, $game] = $this->createGame();
        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/games/{$game->id}", ['name' => 'Hijacked', 'revision' => 1])->assertForbidden();
    }

    public function test_stale_revision_is_rejected_with_current_revision(): void
    {
        [, $game] = $this->createGame();
        $this->patchJson("/api/games/{$game->id}", ['name' => 'Updated', 'revision' => 1])->assertOk();
        $this->patchJson("/api/games/{$game->id}", ['name' => 'Stale', 'revision' => 1])
            ->assertConflict()->assertJsonPath('current_revision', 2);
    }

    public function test_invitation_can_be_created_and_accepted(): void
    {
        [, $game] = $this->createGame();
        $token = $this->postJson("/api/games/{$game->id}/invites", ['team' => 'COMMONWEALTH', 'revision' => 1])
            ->assertCreated()->json('data.token');
        $guest = User::factory()->create();
        Sanctum::actingAs($guest);
        $this->postJson("/api/invites/{$token}/accept")->assertCreated();
        $this->assertDatabaseHas('game_memberships', ['game_id' => $game->id, 'user_id' => $guest->id]);
    }

    public function test_expired_and_invalid_invitations_are_rejected(): void
    {
        [, $game] = $this->createGame();
        $token = $this->postJson("/api/games/{$game->id}/invites", ['revision' => 1, 'privilege' => 'OBSERVER'])->json('data.token');
        GameInvitation::firstOrFail()->update(['expires_at' => now()->subMinute()]);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/invites/{$token}/accept")->assertUnprocessable();
        $this->postJson('/api/invites/not-a-token/accept')->assertUnprocessable();
    }

    public function test_private_team_event_is_not_visible_to_other_team(): void
    {
        [$creator, $game] = $this->createGame();
        $axisMembership = $game->memberships()->firstOrFail();
        app(GameEventRecorder::class)->record($game, 'AxisSecretPrepared', $creator, [], EventVisibility::PrivateTeam, $axisMembership->team_id);

        $other = User::factory()->create();
        $team = $game->teams()->where('code', 'COMMONWEALTH')->firstOrFail();
        GameMembership::create(['game_id' => $game->id, 'user_id' => $other->id, 'team_id' => $team->id]);
        Sanctum::actingAs($other);
        $types = collect($this->getJson("/api/games/{$game->id}/events")->assertOk()->json('data'))->pluck('type');
        $this->assertFalse($types->contains('AxisSecretPrepared'));
    }

    private function createGame(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $scenario = ScenarioDefinition::where('slug', 'the-italians')->firstOrFail();
        $id = $this->postJson('/api/games', ['name' => 'Desert campaign', 'scenario_id' => $scenario->id, 'team' => 'AXIS'])
            ->assertCreated()->json('data.id');

        return [$user, Game::findOrFail($id)];
    }
}
