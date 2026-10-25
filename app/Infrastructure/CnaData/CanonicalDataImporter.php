<?php

namespace App\Infrastructure\CnaData;

use App\Models\ScenarioDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CanonicalDataImporter
{
    public function __construct(
        private readonly CanonicalDataRepository $repository,
        private readonly CanonicalDataValidator $validator,
    ) {}

    /** @return array{units: int, formations: int, equipment: int, setup_units: int, characteristics: int, trucks: int, toe_definitions: int, toe_total: int, toe_partial: int, toe_exceptions: int} */
    public function import(): array
    {
        $counts = $this->validator->validate($this->repository);

        return DB::transaction(function () use ($counts): array {
            $now = now();
            $formationRows = $this->repository->read('formations.json')['formations'];
            foreach ($formationRows as $row) {
                DB::table('formation_definitions')->updateOrInsert(['id' => $row['id']], [
                    'name' => $row['name'], 'side' => $row['side'], 'nationality' => $row['nationality'],
                    'echelon' => $row['echelon'], 'parent_id' => $row['parent_id'], 'oa_chart' => $row['oa_chart'],
                    'provenance' => json_encode($row['provenance'], JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            foreach ($this->repository->read('units.json')['units'] as $row) {
                DB::table('unit_definitions')->updateOrInsert(['id' => $row['id']], [
                    'designation' => $row['designation'], 'display_name' => $row['display_name'], 'side' => $row['side'],
                    'nationality' => $row['nationality'], 'unit_type' => $row['unit_type'], 'echelon' => $row['echelon'],
                    'formation_definition_id' => $row['formation_id'], 'vassal_gpids' => json_encode($row['vassal_gpids'], JSON_THROW_ON_ERROR),
                    'images' => json_encode($row['images'], JSON_THROW_ON_ERROR), 'provenance' => json_encode($row['provenance'], JSON_THROW_ON_ERROR),
                    'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            foreach ($formationRows as $row) {
                DB::table('formation_definitions')->where('id', $row['id'])->update(['headquarters_unit_id' => $row['headquarters_unit_id']]);
            }
            foreach ($this->repository->read('equipment.json')['equipment'] as $row) {
                DB::table('equipment_definitions')->updateOrInsert(['id' => $row['id']], [
                    'name' => $row['name'], 'category' => $row['category'],
                    'characteristics' => isset($row['characteristics']) ? json_encode($row['characteristics'], JSON_THROW_ON_ERROR) : null,
                    'provenance' => json_encode($row['provenance'], JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            foreach ($this->repository->read('characteristics.json')['unit_characteristics'] as $row) {
                $cpa = preg_replace('/[^0-9].*$/', '', (string) $row['cpa']);
                DB::table('unit_characteristic_definitions')->updateOrInsert(['id' => $row['id']], [
                    'nationality' => $row['nationality'], 'id_code' => $row['id_code'], 'cpa' => (int) $cpa,
                    'maximum_toe' => $row['maximum_toe'], 'values' => json_encode(array_diff_key($row, array_flip(['id', 'nationality', 'id_code', 'cpa', 'maximum_toe', 'provenance'])), JSON_THROW_ON_ERROR),
                    'provenance' => json_encode($row['provenance'], JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            foreach ($this->repository->read('truck-characteristics.json')['truck_characteristics'] as $row) {
                DB::table('truck_characteristic_definitions')->updateOrInsert(['id' => $row['id']], [
                    'name' => $row['name'], 'values' => json_encode(array_diff_key($row, array_flip(['id', 'name', 'provenance'])), JSON_THROW_ON_ERROR),
                    'provenance' => json_encode([$this->repository->read('truck-characteristics.json')['source']], JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            foreach ($this->repository->read('unit-toe-definitions.json')['definitions'] as $row) {
                DB::table('unit_toe_definitions')->updateOrInsert(['unit_definition_id' => $row['unit_id']], [
                    'characteristic_definition_id' => $row['characteristic_id'] ?? null,
                    'status' => $row['status'], 'composition' => json_encode($row['composition'] ?? [], JSON_THROW_ON_ERROR),
                    'provenance' => json_encode($row['provenance'] ?? [], JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            $this->importMapReference($now);

            foreach ($this->repository->read('scenarios.json')['scenarios'] as $row) {
                $scenario = ScenarioDefinition::firstOrNew(['slug' => $row['slug']]);
                $scenario->id ??= (string) Str::uuid();
                $scenario->fill([
                    'name' => $row['name'], 'initial_turn' => $row['initial_turn'], 'initial_operation_stage' => $row['initial_operation_stage'],
                    'source_document' => $row['provenance'][0]['document'], 'source_reference' => $row['provenance'][0]['section'],
                    'setup_complete' => $row['completeness'] === 'COMPLETE_FOR_INITIALIZATION',
                ])->save();
                if ($row['setup_file']) {
                    $this->importSetup($scenario, $this->repository->read($row['setup_file']), $now);
                }
            }

            return $counts;
        });
    }

    /** @param array<string, mixed> $setup */
    private function importSetup(ScenarioDefinition $scenario, array $setup, mixed $now): void
    {
        DB::table('scenario_setup_units')->where('scenario_definition_id', $scenario->id)->delete();
        foreach ($setup['units'] as $row) {
            DB::table('scenario_setup_units')->insert([
                'scenario_definition_id' => $scenario->id, 'unit_definition_id' => $row['unit_id'],
                'formation_definition_id' => $row['formation_id'], 'side' => $row['side'],
                'location_type' => $row['location']['kind'], 'location_id' => $row['location']['id'],
                'representation' => $row['representation'], 'initial_statuses' => json_encode($row['initial_statuses'], JSON_THROW_ON_ERROR),
                'toe' => json_encode($row['toe'], JSON_THROW_ON_ERROR), 'provenance' => json_encode($row['provenance'], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $sections = ['attachments' => 'attachments', 'trucks' => 'trucks', 'supplies' => 'supplies', 'aircraft_pools' => 'aircraft_pools', 'pilots' => 'pilots', 'sgsu' => 'sgsu', 'facilities' => 'facilities', 'constructions' => 'constructions', 'broken_down_equipment' => 'equipment_states'];
        foreach ($sections as $source => $tableSuffix) {
            DB::table("scenario_setup_{$tableSuffix}")->where('scenario_definition_id', $scenario->id)->delete();
            foreach ($setup[$source] as $index => $row) {
                $provenance = $row['provenance'] ?? [];
                $data = $row;
                unset($data['provenance']);
                DB::table("scenario_setup_{$tableSuffix}")->insert([
                    'scenario_definition_id' => $scenario->id, 'canonical_key' => "{$source}.{$index}",
                    'data' => json_encode($data, JSON_THROW_ON_ERROR), 'provenance' => json_encode($provenance, JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    private function importMapReference(mixed $now): void
    {
        // The full reference map is intentionally data-heavy (thousands of
        // hex/hexside records). Keep the importer safe under the small local
        // PHP CLI default; deployment memory remains configurable.
        ini_set('memory_limit', '512M');
        $mapFiles = [
            'maps' => ['map_definitions', 'maps', fn (array $row): array => ['name' => $row['name'], 'kind' => $row['kind'], 'provenance' => $row['provenance']]],
            'boards' => ['board_definitions', 'boards', fn (array $row): array => ['name' => $row['name'], 'kind' => $row['kind'], 'image' => $row['image'], 'vassal_path' => $row['vassal_path'], 'provenance' => $row['provenance']]],
            'grids' => ['map_grid_definitions', 'grids', fn (array $row): array => ['section' => $row['section'], 'dx' => $row['dx'], 'dy' => $row['dy'], 'x0' => $row['x0'], 'y0' => $row['y0'], 'sideways' => $row['sideways'], 'snap_to' => $row['snap_to'], 'provenance' => $row['provenance']]],
            'zones' => ['map_zone_definitions', 'zones', fn (array $row): array => ['name' => $row['name'], 'category' => $row['category'], 'polygon' => json_encode($row['polygon'], JSON_THROW_ON_ERROR), 'vassal_path' => $row['vassal_path'], 'provenance' => $row['provenance']]],
            'terrain-definitions' => ['terrain_definitions', 'terrain-definitions', fn (array $row): array => ['name' => $row['name'], 'provenance' => $row['provenance']]],
            'hexes' => ['hex_definitions', 'hexes', fn (array $row): array => ['map_section' => $row['map_section'], 'column' => $row['column'], 'row' => $row['row'], 'neighbors' => json_encode($row['neighbors'], JSON_THROW_ON_ERROR), 'topology_status' => $row['topology_status'], 'terrain_status' => $row['terrain_status'], 'terrain_id' => $row['terrain_id'], 'provenance' => $row['provenance']]],
            'hexsides' => ['hexside_definitions', 'hexsides', fn (array $row): array => ['hex_a' => $row['hex_a'], 'hex_b' => $row['hex_b'], 'features' => json_encode($row['features'], JSON_THROW_ON_ERROR), 'status' => $row['status'], 'provenance' => $row['provenance']]],
            'linear-networks' => ['network_edges', 'linear-networks', fn (array $row): array => ['network_type' => $row['network_type'] ?? 'UNRESOLVED', 'hex_a' => $row['hex_a'] ?? '', 'hex_b' => $row['hex_b'] ?? '', 'properties' => json_encode($row['properties'] ?? [], JSON_THROW_ON_ERROR), 'status' => $row['status'] ?? 'UNRESOLVED_REVIEWED', 'provenance' => $row['provenance'] ?? []]],
            'locations' => ['location_definitions', 'locations', fn (array $row): array => ['name' => $row['name'], 'kind' => $row['kind'], 'map_section' => $row['map_section'] ?? null, 'hex_id' => $row['hex_id'] ?? null, 'area_id' => $row['area_id'] ?? null, 'provenance' => $row['provenance']]],
            'areas' => ['area_definitions', 'areas', fn (array $row): array => ['name' => $row['name'], 'category' => $row['category'], 'polygon' => isset($row['polygon']) ? json_encode($row['polygon'], JSON_THROW_ON_ERROR) : null, 'provenance' => $row['provenance']]],
        ];
        foreach ($mapFiles as [$table, $file, $transform]) {
            $rows = $this->repository->read("map/{$file}.json")[$file] ?? [];
            $payload = [];
            foreach ($rows as $row) {
                $values = $transform($row);
                if (isset($values['provenance']) && is_array($values['provenance'])) {
                    $values['provenance'] = json_encode($values['provenance'], JSON_THROW_ON_ERROR);
                }
                $values['updated_at'] = $now;
                $values['created_at'] = $now;
                $payload[] = ['id' => $row['id'], ...$values];
            }
            foreach (array_chunk($payload, 500) as $chunk) {
                DB::table($table)->upsert($chunk, ['id'], array_keys(array_diff_key($chunk[0], ['id' => true, 'created_at' => true])));
            }
            unset($payload, $rows);
            gc_collect_cycles();
        }
    }
}
