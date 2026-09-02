<?php

namespace App\Http\Controllers;

use App\Http\Requests\MapReviewDecisionRequest;
use App\Models\MapReviewDecision;
use App\Models\MapReviewSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MapReviewController extends Controller
{
    public function regions(): JsonResponse
    {
        $manifest = $this->read('region-work-manifest.json', ['regions' => []]);
        /** @var array<string, array<string, bool>> $progress */
        $progress = [];
        foreach (MapReviewDecision::query()->select('map_section', 'region_id', 'entity_id')->get() as $row) {
            $progress[$row->map_section.'|'.$row->region_id][$row->entity_id] = true;
        }
        foreach ($manifest['regions'] as &$region) {
            $key = $region['map_section'].'|'.$region['batch_id'];
            $region['reviewed_count'] = count($progress[$key] ?? []);
            $region['total_count'] = count($region['hex_ids'] ?? []);
        }

        return response()->json(['data' => $manifest]);
    }

    public function region(string $region): JsonResponse
    {
        $manifest = null;
        foreach ($this->read('region-work-manifest.json', ['regions' => []])['regions'] as $candidate) {
            if (($candidate['batch_id'] ?? null) === $region) {
                $manifest = $candidate;
                break;
            }
        }
        abort_unless($manifest, 404);
        $hexes = array_values(array_filter($this->read('hexes.json', ['hexes' => []])['hexes'], fn (array $row): bool => in_array($row['id'] ?? null, $manifest['hex_ids'], true)));
        $decisions = MapReviewDecision::query()->where('region_id', $region)->get();
        $locations = array_values(array_filter($this->read('locations.json', ['locations' => []])['locations'], fn (array $row): bool => in_array($row['hex_id'] ?? null, $manifest['hex_ids'], true)));

        $terrainDefinitions = $this->read('terrain-definitions.json', ['terrain-definitions' => []])['terrain-definitions'];
        $visualReference = $this->read('terrain-visual-reference.json', ['entries' => []]);

        return response()->json(['data' => ['region' => $manifest, 'hexes' => $hexes, 'decisions' => $decisions, 'terrain_definitions' => $terrainDefinitions, 'terrain_visual_reference' => $visualReference['entries'], 'locations' => $locations]]);
    }

    public function hex(string $hex): JsonResponse
    {
        $row = null;
        foreach ($this->read('hexes.json', ['hexes' => []])['hexes'] as $candidate) {
            if (($candidate['id'] ?? null) === $hex) {
                $row = $candidate;
                break;
            }
        }
        abort_unless($row, 404);

        return response()->json(['data' => ['hex' => $row, 'decisions' => MapReviewDecision::query()->where('entity_type', 'hex')->where('entity_id', $hex)->get()]]);
    }

    public function startSession(Request $request): JsonResponse
    {
        $data = $request->validate(['map_section' => ['required', 'string'], 'region_id' => ['nullable', 'string', 'max:80']]);
        $session = MapReviewSession::create(['reviewer_id' => $request->user()->id, 'map_section' => $data['map_section'], 'region_id' => $data['region_id'] ?? null, 'status' => 'OPEN', 'started_at' => now()]);

        return response()->json(['data' => $session], 201);
    }

    public function decision(MapReviewDecisionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertEntity($data);
        $decision = MapReviewDecision::create([...$data, 'reviewer_id' => $request->user()->id]);

        return response()->json(['data' => $decision], 201);
    }

    public function bulk(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'decisions' => ['required', 'array', 'min:1', 'max:500'],
            'decisions.*.session_id' => ['nullable', 'uuid'],
            'decisions.*.map_section' => ['required', 'string', 'in:Map A,Map D,Map E'],
            'decisions.*.region_id' => ['nullable', 'string', 'max:80'],
            'decisions.*.entity_type' => ['required', 'string', 'in:hex,hexside,network,location'],
            'decisions.*.entity_id' => ['required', 'string', 'max:120'],
            'decisions.*.decision' => ['required', 'string', 'in:CONFIRMED,AMBIGUOUS,UNRESOLVED,SKIPPED'],
            'decisions.*.value' => ['nullable', 'array'],
            'decisions.*.notes' => ['nullable', 'string', 'max:5000'],
            'decisions.*.source' => ['nullable', 'array'],
        ])->validate();
        $actionGroupId = (string) ($request->input('action_group_id') ?: Str::uuid());

        $created = DB::transaction(function () use ($validated, $request, $actionGroupId): array {
            $seen = [];
            $rows = [];
            foreach ($validated['decisions'] as $data) {
                $this->assertEntity($data);
                $key = $data['entity_type'].'|'.$data['entity_id'];
                $valueJson = json_encode($data['value'] ?? null, JSON_THROW_ON_ERROR);
                if (isset($seen[$key]) && $seen[$key] !== $valueJson) {
                    throw ValidationException::withMessages(['decisions' => "Conflicting values in bulk action for {$key}."]);
                }
                $seen[$key] = $valueJson;
                if (($data['decision'] ?? null) === 'CONFIRMED') {
                    $conflicting = MapReviewDecision::query()->where('entity_type', $data['entity_type'])->where('entity_id', $data['entity_id'])->where('decision', 'CONFIRMED')->get()->first(function (MapReviewDecision $existing) use ($valueJson): bool {
                        return json_encode($existing->value, JSON_THROW_ON_ERROR) !== $valueJson;
                    });
                    if ($conflicting) {
                        throw ValidationException::withMessages(['decisions' => "Existing review conflict for {$key}."]);
                    }
                }
                $rows[] = MapReviewDecision::create([...$data, 'reviewer_id' => $request->user()->id, 'action_group_id' => $actionGroupId]);
            }

            return $rows;
        });

        return response()->json(['data' => ['action_group_id' => $actionGroupId, 'decisions' => $created, 'count' => count($created)]], 201);
    }

    public function progress(): JsonResponse
    {
        $rows = MapReviewDecision::query()->select('map_section', 'region_id', 'entity_type', 'entity_id', 'decision')->get();

        return response()->json(['data' => ['total_decisions' => $rows->count(), 'confirmed' => $rows->where('decision', 'CONFIRMED')->count(), 'ambiguous' => $rows->where('decision', 'AMBIGUOUS')->count(), 'unresolved' => $rows->where('decision', 'UNRESOLVED')->count(), 'skipped' => $rows->where('decision', 'SKIPPED')->count(), 'reviewed_entities' => $rows->groupBy(fn ($row) => $row->entity_type.'|'.$row->entity_id)->count()]]);
    }

    public function conflicts(): JsonResponse
    {
        /** @var array<string, array<int, MapReviewDecision>> $groups */
        $groups = [];
        foreach (MapReviewDecision::query()->get() as $row) {
            $groups[$row->entity_type.'|'.$row->entity_id][] = $row;
        }
        $conflicts = [];
        foreach ($groups as $rows) {
            $values = array_unique(array_map(fn (MapReviewDecision $row): string => json_encode($row->value), $rows));
            if (count($values) > 1) {
                $conflicts[] = $rows;
            }
        }

        return response()->json(['data' => $conflicts]);
    }

    public function export(): JsonResponse
    {
        return response()->json(['schema_version' => 1, 'exported_at' => now()->toIso8601String(), 'decisions' => MapReviewDecision::query()->get()]);
    }

    /** @param array<string, mixed> $data */
    private function assertEntity(array $data): void
    {
        $map = json_decode(file_get_contents(resource_path('cna/map/hexes.json')), true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, array<string, mixed>> $ids */
        $ids = [];
        foreach ($map['hexes'] as $row) {
            $ids[$row['id']] = $row;
        }
        if ($data['entity_type'] === 'hex') {
            abort_unless(isset($ids[$data['entity_id']]), 422, 'Unknown canonical hex.');
            if ($data['decision'] === 'CONFIRMED') {
                $terrain = $data['value']['terrain_id'] ?? null;
                $valid = array_column($this->read('terrain-definitions.json', ['terrain-definitions' => []])['terrain-definitions'], 'id');
                abort_unless(in_array($terrain, $valid, true), 422, 'Unknown terrain definition.');
            }
        } elseif ($data['entity_type'] === 'hexside') {
            $sides = array_column($this->read('hexsides.json', ['hexsides' => []])['hexsides'], 'id');
            abort_unless(in_array($data['entity_id'], $sides, true), 422, 'Unknown canonical hexside.');
        } elseif ($data['entity_type'] === 'network') {
            $a = $data['value']['hex_a'] ?? null;
            $b = $data['value']['hex_b'] ?? null;
            abort_unless(isset($ids[$a], $ids[$b]) && in_array($b, $ids[$a]['neighbors'] ?? [], true), 422, 'Network endpoints must be canonical neighbors.');
            abort_unless(in_array($data['value']['network_type'] ?? null, ['ROAD', 'TRACK', 'RAIL', 'PIPELINE'], true), 422, 'Unknown network type.');
        }
    }

    /** @param array<string, mixed> $fallback
     * @return array<string, mixed> */
    private function read(string $file, array $fallback): array
    {
        $path = resource_path('cna/map/'.$file);

        return is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : $fallback;
    }
}
