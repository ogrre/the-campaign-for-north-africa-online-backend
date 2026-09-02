<?php

namespace App\Application\Scenario;

use App\Infrastructure\CnaData\CanonicalDataRepository;
use App\Models\Game;
use App\Models\GameTurnState;
use App\Models\ScenarioDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CanonicalScenarioInitializer implements ScenarioInitializer
{
    public function initialize(Game $game, ScenarioDefinition $scenario): void
    {
        DB::transaction(fn () => $this->initializeTransaction($game, $scenario));
    }

    private function initializeTransaction(Game $game, ScenarioDefinition $scenario): void
    {
        if (! $scenario->setup_complete || $scenario->initial_turn === null || $scenario->initial_operation_stage === null) {
            throw ValidationException::withMessages(['scenario' => 'The scenario has no complete canonical setup.']);
        }
        if (DB::table('scenario_initializations')->where('game_id', $game->id)->exists()) {
            throw ValidationException::withMessages(['scenario' => 'This game has already been initialized.']);
        }

        $setupUnits = DB::table('scenario_setup_units')->where('scenario_definition_id', $scenario->id)->orderBy('id')->get();
        if ($setupUnits->isEmpty()) {
            throw ValidationException::withMessages(['scenario' => 'The canonical scenario setup has not been imported.']);
        }

        GameTurnState::create([
            'game_id' => $game->id,
            'turn' => $scenario->initial_turn,
            'operation_stage' => $scenario->initial_operation_stage,
            'phase' => 'AWAITING_INITIAL_DECISIONS',
        ]);
        $setup = app(CanonicalDataRepository::class)
            ->read('scenario-setups/'.$scenario->slug.'.json');
        $toeDefinitions = [];
        try {
            foreach (app(CanonicalDataRepository::class)->read('unit-toe-definitions.json')['definitions'] ?? [] as $definition) {
                if (isset($definition['unit_id'])) {
                    $toeDefinitions[$definition['unit_id']] = $definition;
                }
            }
        } catch (\Throwable) {
            // Older datasets may not ship TOE definitions; scenario setup data
            // remains the compatibility fallback below.
        }
        DB::table('game_scenario_states')->insert([
            'game_id' => $game->id,
            'initiative' => json_encode($setup['initiative'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $formationIds = [];
        foreach ($setupUnits->pluck('formation_definition_id')->filter()->unique() as $definitionId) {
            $formationIds[$definitionId] = (string) Str::uuid();
            DB::table('game_formations')->insert([
                'id' => $formationIds[$definitionId], 'game_id' => $game->id,
                'formation_definition_id' => $definitionId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $unitIds = [];
        foreach ($setupUnits as $row) {
            $gameUnitId = (string) Str::uuid();
            $unitIds[$row->unit_definition_id] = $gameUnitId;
            DB::table('game_units')->insert([
                'id' => $gameUnitId, 'game_id' => $game->id, 'unit_definition_id' => $row->unit_definition_id,
                'game_formation_id' => $row->formation_definition_id ? $formationIds[$row->formation_definition_id] : null,
                'side' => $row->side, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('game_unit_states')->insert([
                'game_unit_id' => $gameUnitId, 'location_type' => $row->location_type, 'location_id' => $row->location_id,
                'statuses' => $row->initial_statuses, 'cohesion' => null, 'morale' => null, 'cpa_spent' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($row->formation_definition_id) {
                DB::table('game_unit_assignments')->insert([
                    'game_unit_id' => $gameUnitId, 'game_formation_id' => $formationIds[$row->formation_definition_id],
                    'assigned_at' => now(), 'ended_at' => null,
                ]);
            }
            $canonicalToe = $toeDefinitions[$row->unit_definition_id] ?? null;
            $toeRows = ($canonicalToe !== null && ($canonicalToe['resolution_status'] ?? null) === 'RESOLVED')
                ? ($canonicalToe['composition'] ?? [])
                : json_decode($row->toe, true, 512, JSON_THROW_ON_ERROR);
            foreach ($toeRows as $toe) {
                DB::table('game_unit_toe')->insert([
                    'game_unit_id' => $gameUnitId, 'equipment_definition_id' => $toe['equipment_id'] ?? null,
                    'strength_points' => $toe['strength_points'] ?? $toe['toe_points'] ?? 0,
                    'state' => $toe['state'] ?? 'AVAILABLE',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach ($this->componentRows($scenario, 'attachments') as $row) {
            DB::table('game_unit_attachments')->insert([
                'game_unit_id' => $unitIds[$row['unit_id']], 'attached_to_game_unit_id' => $unitIds[$row['attached_to_unit_id']],
                'attached_at' => now(), 'ended_at' => null,
            ]);
        }

        $components = [
            'trucks' => 'truck_pools', 'supplies' => 'supply_stocks', 'aircraft_pools' => 'aircraft_pools',
            'pilots' => 'pilot_pools', 'sgsu' => 'sgsu_pools', 'facilities' => 'facilities',
            'constructions' => 'constructions', 'equipment_states' => 'equipment_states',
        ];
        foreach ($components as $source => $target) {
            foreach (DB::table("scenario_setup_{$source}")->where('scenario_definition_id', $scenario->id)->get() as $row) {
                DB::table("game_{$target}")->insert([
                    'id' => (string) Str::uuid(), 'game_id' => $game->id, 'canonical_key' => $row->canonical_key,
                    'state' => $row->data, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        DB::table('scenario_initializations')->insert([
            'game_id' => $game->id, 'scenario_definition_id' => $scenario->id,
            'dataset_version' => '2026.08.19', 'initialized_at' => now(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function componentRows(ScenarioDefinition $scenario, string $component): array
    {
        return DB::table("scenario_setup_{$component}")->where('scenario_definition_id', $scenario->id)->orderBy('id')->get()
            ->map(fn ($row): array => json_decode($row->data, true, 512, JSON_THROW_ON_ERROR))->all();
    }
}
