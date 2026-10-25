<?php

namespace App\Infrastructure\CnaData;

use RuntimeException;

final class CanonicalDataValidator
{
    /** @return array{units: int, formations: int, equipment: int, setup_units: int, characteristics: int, trucks: int, toe_definitions: int, toe_total: int, toe_partial: int, toe_exceptions: int} */
    public function validate(CanonicalDataRepository $repository): array
    {
        $units = $repository->read('units.json')['units'] ?? [];
        $formations = $repository->read('formations.json')['formations'] ?? [];
        $equipment = $repository->read('equipment.json')['equipment'] ?? [];
        $scenarios = $repository->read('scenarios.json')['scenarios'] ?? [];
        $reinforcements = $repository->read('reinforcements.json')['reinforcements'] ?? [];
        $conflicts = $repository->read('conflicts.json')['conflicts'] ?? [];
        $characteristics = $repository->read('characteristics.json')['unit_characteristics'] ?? [];
        $trucks = $repository->read('truck-characteristics.json')['truck_characteristics'] ?? [];
        $toe = $repository->read('unit-toe-definitions.json');
        $setup = $repository->read('scenario-setups/the-italians.json');
        $unitOa = $repository->read('unit-oa-mappings.json')['mappings'] ?? [];
        $unitCharacteristics = $repository->read('unit-characteristic-mappings.json')['mappings'] ?? [];
        $unitIds = $this->uniqueIds($units, 'unit');
        $formationIds = $this->uniqueIds($formations, 'formation');
        $equipmentIds = $this->uniqueIds($equipment, 'equipment');
        $characteristicIds = $this->uniqueIds($characteristics, 'characteristic');
        $this->uniqueIds($trucks, 'truck characteristic');
        $this->uniqueIds($scenarios, 'scenario', 'slug');
        $unitMappingIds = [];
        foreach ($unitOa as $mapping) {
            if (! isset($unitIds[$mapping['unit_id'] ?? ''])) {
                throw new RuntimeException('Mapping references unknown unit '.($mapping['unit_id'] ?? '<missing>'));
            }
            $this->requireProvenance($mapping, "unit OA mapping {$mapping['unit_id']}");
        }
        foreach ($unitCharacteristics as $mapping) {
            if (! isset($unitIds[$mapping['unit_id'] ?? ''])) {
                throw new RuntimeException('Mapping references unknown unit '.($mapping['unit_id'] ?? '<missing>'));
            }
            $mappingKey = $mapping['unit_id'] ?? '';
            if (isset($unitMappingIds[$mappingKey])) {
                throw new RuntimeException("Duplicate unit mapping {$mappingKey}");
            }
            $unitMappingIds[$mappingKey] = true;
            $this->requireProvenance($mapping, "unit mapping {$mapping['unit_id']}");
        }
        foreach ($unitCharacteristics as $mapping) {
            if (($mapping['characteristic_id'] ?? null) && ! isset($characteristicIds[$mapping['characteristic_id']])) {
                throw new RuntimeException("Mapping references unknown characteristic {$mapping['characteristic_id']}");
            }
        }

        foreach ($units as $unit) {
            $this->requireProvenance($unit, "unit {$unit['id']}");
            if (($unit['formation_id'] ?? null) && ! isset($formationIds[$unit['formation_id']])) {
                throw new RuntimeException("Unknown formation {$unit['formation_id']} on unit {$unit['id']}");
            }
        }
        foreach ($characteristics as $characteristic) {
            $this->requireProvenance($characteristic, "characteristic {$characteristic['id']}");
            foreach (['cpa', 'anti_air', 'barrage', 'anti_armor', 'vulnerability', 'armor_protection', 'close_assault', 'maximum_toe'] as $field) {
                if (! array_key_exists($field, $characteristic)) {
                    throw new RuntimeException("Missing characteristic field {$field} on {$characteristic['id']}");
                }
            }
        }
        foreach ($trucks as $truck) {
            $this->requireProvenance($truck, "truck characteristic {$truck['id']}");
        }
        foreach ($toe['definitions'] ?? [] as $definition) {
            if (! isset($unitIds[$definition['unit_id']])) {
                throw new RuntimeException("TOE references unknown unit {$definition['unit_id']}");
            }
            if (isset($definition['characteristic_id']) && ! isset($characteristicIds[$definition['characteristic_id']])) {
                throw new RuntimeException("TOE references unknown characteristic {$definition['characteristic_id']}");
            }
            $this->requireProvenance($definition, "TOE {$definition['unit_id']}");
        }
        foreach ($formations as $formation) {
            $this->requireProvenance($formation, "formation {$formation['id']}");
            if (($formation['parent_id'] ?? null) && ! isset($formationIds[$formation['parent_id']])) {
                throw new RuntimeException("Unknown parent on formation {$formation['id']}");
            }
            if (! isset($unitIds[$formation['headquarters_unit_id']])) {
                throw new RuntimeException("Unknown headquarters on formation {$formation['id']}");
            }
        }
        foreach ($setup['units'] ?? [] as $row) {
            if (! isset($unitIds[$row['unit_id']])) {
                throw new RuntimeException("Unknown setup unit {$row['unit_id']}");
            }
            if (($row['formation_id'] ?? null) && ! isset($formationIds[$row['formation_id']])) {
                throw new RuntimeException("Unknown setup formation {$row['formation_id']}");
            }
            if ($row['location']['kind'] === 'HEX' && ! preg_match('/^[A-E]\d{4}$/', $row['location']['id'])) {
                throw new RuntimeException("Invalid setup hex {$row['location']['id']}");
            }
            $this->requireProvenance($row, "setup unit {$row['unit_id']}");
        }
        foreach (['aircraft_pools', 'broken_down_equipment'] as $section) {
            foreach ($setup[$section] ?? [] as $row) {
                if (! isset($equipmentIds[$row['equipment_id']])) {
                    throw new RuntimeException("Unknown {$section} equipment {$row['equipment_id']}");
                }
            }
        }
        foreach ($reinforcements as $reinforcement) {
            if (! isset($unitIds[$reinforcement['unit_id']])) {
                throw new RuntimeException("Unknown reinforcement unit {$reinforcement['unit_id']}");
            }
            $this->requireProvenance($reinforcement, "reinforcement {$reinforcement['unit_id']}");
        }
        foreach (['attachments', 'trucks', 'supplies', 'aircraft_pools', 'pilots', 'sgsu', 'facilities', 'constructions', 'broken_down_equipment'] as $section) {
            foreach ($setup[$section] ?? [] as $index => $row) {
                $this->requireProvenance($row, "setup {$section}.{$index}");
            }
        }
        foreach ($conflicts as $conflict) {
            if (! in_array($conflict['status'] ?? null, ['OPEN', 'RESOLVED', 'DEFERRED'], true)) {
                throw new RuntimeException("Invalid conflict status on {$conflict['id']}");
            }
            if (($conflict['status'] ?? null) === 'RESOLVED' && (empty($conflict['resolution']) || empty($conflict['resolution_basis']))) {
                throw new RuntimeException("Resolved conflict {$conflict['id']} has no resolution");
            }
            $this->requireProvenance($conflict, "conflict {$conflict['id']}");
        }

        $completeToe = count(array_filter($toe['definitions'] ?? [], static fn (array $definition): bool => ($definition['resolution_status'] ?? null) === 'RESOLVED' || ($definition['resolution_status'] ?? null) === null && ($definition['status'] ?? null) === 'OA_TRANSCRIBED'));
        $partialToe = count($toe['definitions'] ?? []) - $completeToe;

        return ['units' => count($units), 'formations' => count($formations), 'equipment' => count($equipment), 'setup_units' => count($setup['units'] ?? []), 'characteristics' => count($characteristics), 'trucks' => count($trucks), 'toe_definitions' => $completeToe, 'toe_total' => count($toe['definitions'] ?? []), 'toe_partial' => $partialToe, 'toe_exceptions' => count($toe['unresolved'] ?? [])];
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<string, true>
     */
    private function uniqueIds(array $rows, string $label, string $field = 'id'): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $row[$field] ?? null;
            if (! is_string($id) || $id === '' || isset($ids[$id])) {
                throw new RuntimeException("Missing or duplicate canonical {$label} identifier: ".var_export($id, true));
            }
            $ids[$id] = true;
        }

        return $ids;
    }

    /** @param array<string, mixed> $row */
    private function requireProvenance(array $row, string $label): void
    {
        if (empty($row['provenance']) || ! is_array($row['provenance'])) {
            throw new RuntimeException("Missing provenance on {$label}");
        }
    }
}
