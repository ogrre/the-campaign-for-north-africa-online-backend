<?php

namespace Tests\Feature;

use App\Application\Scenario\ScenarioInitializer;
use App\Infrastructure\CnaData\CanonicalDataImporter;
use App\Models\Game;
use App\Models\GameMembership;
use App\Models\ScenarioDefinition;
use App\Models\User;
use Database\Seeders\MultiplayerReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CanonicalScenarioInitializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MultiplayerReferenceSeeder::class);
        app(CanonicalDataImporter::class)->import();
    }

    public function test_import_is_idempotent_and_the_italians_matches_the_golden_master(): void
    {
        app(CanonicalDataImporter::class)->import();

        $this->assertDatabaseCount('unit_definitions', 290);
        $this->assertDatabaseCount('formation_definitions', 16);
        $this->assertDatabaseCount('equipment_definitions', 104);
        $this->assertDatabaseCount('unit_characteristic_definitions', 123);
        $this->assertDatabaseCount('truck_characteristic_definitions', 3);
        $this->assertDatabaseCount('unit_toe_definitions', 272);
        $this->assertDatabaseHas('equipment_definitions', ['id' => 'weapon.german.pz_iii_e']);
        $this->assertDatabaseHas('unit_characteristic_definitions', ['id' => 'unit.german.j']);
        $this->assertDatabaseCount('scenario_setup_units', 288);
        $this->assertSame(195, DB::table('scenario_setup_units')->where('side', 'AXIS')->count());
        $this->assertSame(93, DB::table('scenario_setup_units')->where('side', 'COMMONWEALTH')->count());
    }

    public function test_start_initializes_all_runtime_state_and_exposes_read_only_oob(): void
    {
        [$creator, $game] = $this->readyGame();
        Sanctum::actingAs($creator);
        $this->postJson("/api/games/{$game->id}/start", ['revision' => 4])->assertOk();

        $this->assertDatabaseCount('scenario_initializations', 1);
        $this->assertSame(288, DB::table('game_units')->where('game_id', $game->id)->count());
        $this->assertSame(9, DB::table('game_unit_toe')->whereIn('game_unit_id', DB::table('game_units')->where('game_id', $game->id)->pluck('id'))->count());
        $this->assertSame(16, DB::table('game_formations')->where('game_id', $game->id)->count());
        $this->assertSame(32, DB::table('game_truck_pools')->where('game_id', $game->id)->count());
        $this->assertSame(15, DB::table('game_supply_stocks')->where('game_id', $game->id)->count());
        $this->assertSame(60, DB::table('game_facilities')->where('game_id', $game->id)->count());
        $this->assertSame(24, DB::table('game_aircraft_pools')->where('game_id', $game->id)->count());
        $this->assertSame(8, DB::table('game_pilot_pools')->where('game_id', $game->id)->count());
        $this->assertSame(3, DB::table('game_sgsu_pools')->where('game_id', $game->id)->count());
        $this->assertDatabaseHas('game_events', ['game_id' => $game->id, 'type' => 'ScenarioInitialized']);
        $this->assertDatabaseHas('game_events', ['game_id' => $game->id, 'type' => 'GameStarted']);

        $this->getJson("/api/games/{$game->id}/order-of-battle")->assertOk()
            ->assertJsonPath('data.counts.units', 288)
            ->assertJsonPath('data.counts.axis_units', 195)
            ->assertJsonPath('data.counts.commonwealth_units', 93)
            ->assertJsonCount(32, 'data.components.truck_pools');
    }

    public function test_double_initialization_is_rejected(): void
    {
        [, $game] = $this->readyGame();
        $initializer = app(ScenarioInitializer::class);
        $initializer->initialize($game, $game->scenario);

        $this->expectException(ValidationException::class);
        $initializer->initialize($game, $game->scenario);
    }

    public function test_start_rolls_back_and_never_records_game_started_when_initialization_fails(): void
    {
        [$creator, $game] = $this->readyGame();
        $this->app->bind(ScenarioInitializer::class, fn () => new class implements ScenarioInitializer
        {
            public function initialize(Game $game, ScenarioDefinition $scenario): void
            {
                DB::table('game_turn_states')->insert(['game_id' => $game->id, 'turn' => 99, 'operation_stage' => 9, 'phase' => 'BROKEN', 'created_at' => now(), 'updated_at' => now()]);
                throw new RuntimeException('initializer failure');
            }
        });
        Sanctum::actingAs($creator);

        $this->withoutExceptionHandling();
        try {
            $this->postJson("/api/games/{$game->id}/start", ['revision' => 4]);
            self::fail('Expected initializer failure');
        } catch (RuntimeException $exception) {
            self::assertSame('initializer failure', $exception->getMessage());
        }
        $this->assertDatabaseMissing('game_turn_states', ['game_id' => $game->id]);
        $this->assertDatabaseMissing('game_events', ['game_id' => $game->id, 'type' => 'GameStarted']);
        self::assertSame('lobby', $game->fresh()->status->value);
    }

    /** @return array{User, Game} */
    private function readyGame(): array
    {
        $creator = User::factory()->create();
        Sanctum::actingAs($creator);
        $scenario = ScenarioDefinition::where('slug', 'the-italians')->firstOrFail();
        $gameId = $this->postJson('/api/games', ['name' => 'Golden master', 'scenario_id' => $scenario->id, 'team' => 'AXIS'])->assertCreated()->json('data.id');
        $game = Game::findOrFail($gameId);
        $axis = $game->memberships()->firstOrFail();
        $this->postJson("/api/games/{$game->id}/players/{$axis->id}/roles", ['role' => 'COMMANDER_IN_CHIEF', 'revision' => 1])->assertCreated();
        $commonwealthUser = User::factory()->create();
        Sanctum::actingAs($commonwealthUser);
        $this->postJson("/api/games/{$game->id}/join", ['team' => 'COMMONWEALTH', 'revision' => 2])->assertCreated();
        $commonwealth = GameMembership::where('user_id', $commonwealthUser->id)->firstOrFail();
        Sanctum::actingAs($creator);
        $this->postJson("/api/games/{$game->id}/players/{$commonwealth->id}/roles", ['role' => 'COMMANDER_IN_CHIEF', 'revision' => 3])->assertCreated();

        return [$creator, $game->fresh('scenario')];
    }
}
