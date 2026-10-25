<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class GameOrderOfBattleController extends Controller
{
    public function __invoke(Game $game): JsonResponse
    {
        $this->authorize('view', $game);

        $units = DB::table('game_units as gu')
            ->join('unit_definitions as ud', 'ud.id', '=', 'gu.unit_definition_id')
            ->leftJoin('game_formations as gf', 'gf.id', '=', 'gu.game_formation_id')
            ->leftJoin('formation_definitions as fd', 'fd.id', '=', 'gf.formation_definition_id')
            ->join('game_unit_states as state', 'state.game_unit_id', '=', 'gu.id')
            ->where('gu.game_id', $game->id)
            ->orderBy('gu.side')->orderBy('ud.display_name')
            ->select([
                'gu.id', 'gu.side', 'ud.id as definition_id', 'ud.display_name', 'ud.designation',
                'ud.nationality', 'ud.unit_type', 'ud.echelon', 'ud.provenance as unit_provenance', 'fd.id as formation_id',
                'fd.name as formation_name', 'state.location_type', 'state.location_id', 'state.statuses',
            ])->get()->map(function (object $unit): array {
                $toe = DB::table('game_unit_toe as toe')
                    ->leftJoin('equipment_definitions as equipment', 'equipment.id', '=', 'toe.equipment_definition_id')
                    ->where('toe.game_unit_id', $unit->id)
                    ->select('toe.equipment_definition_id', 'equipment.name', 'toe.strength_points', 'toe.state')->get();

                return [
                    ...((array) $unit),
                    'statuses' => is_string($unit->statuses) ? json_decode($unit->statuses, true, 512, JSON_THROW_ON_ERROR) : $unit->statuses,
                    'unit_provenance' => is_string($unit->unit_provenance) ? json_decode($unit->unit_provenance, true, 512, JSON_THROW_ON_ERROR) : $unit->unit_provenance,
                    'characteristics' => null,
                    'toe' => $toe,
                ];
            });

        $components = [];
        foreach (['truck_pools', 'supply_stocks', 'aircraft_pools', 'pilot_pools', 'sgsu_pools', 'facilities', 'constructions', 'equipment_states'] as $name) {
            $components[$name] = DB::table("game_{$name}")->where('game_id', $game->id)->orderBy('canonical_key')->get()
                ->map(fn (object $row): array => [
                    'canonical_key' => $row->canonical_key,
                    'state' => is_string($row->state) ? json_decode($row->state, true, 512, JSON_THROW_ON_ERROR) : $row->state,
                ]);
        }

        return response()->json(['data' => [
            'game_id' => $game->id,
            'scenario' => $game->scenario()->firstOrFail()->only(['slug', 'name']),
            'turn' => $game->turnState?->turn,
            'operation_stage' => $game->turnState?->operation_stage,
            'counts' => ['units' => $units->count(), 'axis_units' => $units->where('side', 'AXIS')->count(), 'commonwealth_units' => $units->where('side', 'COMMONWEALTH')->count()],
            'units' => $units,
            'components' => $components,
        ]]);
    }
}
