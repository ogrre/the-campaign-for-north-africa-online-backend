<?php

namespace App\Console\Commands;

use App\Infrastructure\CnaData\CanonicalDataRepository;
use Illuminate\Console\Command;

final class CnaCoverage extends Command
{
    protected $signature = 'cna:coverage {scenario=the-italians}';

    protected $description = 'Show prioritized canonical coverage for a scenario';

    public function handle(CanonicalDataRepository $repository): int
    {
        $scenario = (string) $this->argument('scenario');
        if ($scenario === 'map') {
            return $this->mapCoverage($repository);
        }
        $setup = $repository->read("scenario-setups/{$scenario}.json");
        $units = [];
        foreach ($repository->read('units.json')['units'] ?? [] as $unit) {
            $units[$unit['id']] = $unit;
        }
        $ids = [];
        foreach ($setup['units'] ?? [] as $row) {
            if (($units[$row['unit_id']]['unit_type'] ?? null) !== 'NAVAL') {
                $ids[] = $row['unit_id'];
            }
        }
        $oa = $this->index($repository->read('unit-oa-mappings.json')['mappings'] ?? []);
        $characteristics = $this->index($repository->read('unit-characteristic-mappings.json')['mappings'] ?? []);
        $toe = $this->index($repository->read('unit-toe-definitions.json')['definitions'] ?? []);
        $equipment = $this->index($repository->read('unit-equipment-mappings.json')['mappings'] ?? []);
        $this->line($scenario === 'the-italians' ? 'The Italians' : $scenario);
        $this->line('-------------');
        $this->line(sprintf('Land units:                %d', count($ids)));
        $this->line(sprintf('OA resolved:               %d', $this->countMatching($ids, $oa, fn (array $row): bool => ($row['status'] ?? null) === 'EXACT')));
        $this->line(sprintf('ID Code resolved:          %d', $this->countMatching($ids, $characteristics, fn (array $row): bool => in_array($row['status'] ?? null, ['EXACT_OA_ID_CODE', 'MANUAL_CONFIRMED'], true))));
        $this->line(sprintf('Characteristics resolved:  %d', $this->countMatching($ids, $characteristics, fn (array $row): bool => isset($row['characteristic_id']))));
        $this->line(sprintf('TOE resolved:              %d', $this->countMatching($ids, $toe, fn (array $row): bool => ($row['resolution_status'] ?? null) === 'RESOLVED')));
        $this->line(sprintf('TOE partial:               %d', $this->countMatching($ids, $toe, fn (array $row): bool => ($row['resolution_status'] ?? null) === 'PARTIAL')));
        $this->line(sprintf('TOE unresolved reviewed:   %d', $this->countMatching($ids, $toe, fn (array $row): bool => ($row['resolution_status'] ?? null) === 'UNRESOLVED_REVIEWED')));
        $this->line(sprintf('TOE not applicable:        %d', $this->countMatching($ids, $toe, fn (array $row): bool => ($row['resolution_status'] ?? null) === 'NOT_APPLICABLE')));
        $this->line(sprintf('TOE not reviewed:          %d', count($ids) - $this->countMatching($ids, $toe, fn (array $row): bool => isset($row['resolution_status']))));
        $this->line(sprintf('Equipment resolved:        %d', $this->countMatching($ids, $equipment, fn (array $row): bool => in_array($row['status'] ?? null, ['RESOLVED', 'EXACT'], true))));
        $this->line(sprintf('Equipment ambiguous:       %d', $this->countMatching($ids, $equipment, fn (array $row): bool => in_array($row['status'] ?? null, ['REVIEW_REQUIRED', 'UNRESOLVED_REVIEWED'], true))));
        $resolved = $this->countMatching($ids, $characteristics, fn (array $row): bool => isset($row['characteristic_id']));
        $reviewedUnresolved = $this->countMatching($ids, $characteristics, fn (array $row): bool => in_array($row['resolution_status'] ?? $row['status'] ?? null, ['UNRESOLVED_REVIEWED', 'MISSING_FROM_AVAILABLE_SOURCES', 'AMBIGUOUS', 'CONFLICT', 'NOT_APPLICABLE'], true));
        $this->line(sprintf('Unresolved reviewed:       %d', $reviewedUnresolved));
        $this->line(sprintf('Not reviewed:              %d', count($ids) - $resolved - $reviewedUnresolved));
        $this->line(sprintf('Needs review:              %d', count($ids) - $resolved));

        return self::SUCCESS;
    }

    private function mapCoverage(CanonicalDataRepository $repository): int
    {
        $count = static fn (string $file, string $key): int => count($repository->read("map/{$file}.json")[$key] ?? []);
        $hexes = $repository->read('map/hexes.json')['hexes'] ?? [];
        $this->line('Canonical map');
        $this->line('-------------');
        $this->line(sprintf('Maps:                       %d', $count('maps', 'maps')));
        $this->line(sprintf('Boards:                     %d', $count('boards', 'boards')));
        $this->line(sprintf('Grids:                      %d', $count('grids', 'grids')));
        $this->line(sprintf('Hexes total:                %d', count($hexes)));
        $this->line(sprintf('Topology resolved:          %d', count(array_filter($hexes, fn (array $row): bool => ($row['topology_status'] ?? null) === 'RESOLVED'))));
        $this->line(sprintf('Terrain resolved:           %d', count(array_filter($hexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'RESOLVED'))));
        $this->line(sprintf('Terrain partial:            %d', count(array_filter($hexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'PARTIAL'))));
        $this->line(sprintf('Terrain ambiguous:          %d', count(array_filter($hexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'AMBIGUOUS'))));
        $this->line(sprintf('Terrain unresolved reviewed:%d', count(array_filter($hexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'UNRESOLVED_REVIEWED'))));
        $this->line(sprintf('Terrain not reviewed:        %d', count(array_filter($hexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'NOT_REVIEWED'))));
        $this->line(sprintf('Terrain definitions:         %d', $count('terrain-definitions', 'terrain-definitions')));
        $this->line(sprintf('Hexsides:                   %d', $count('hexsides', 'hexsides')));
        $this->line(sprintf('Road edges:                 %d', $this->networkCount($repository, 'ROAD')));
        $this->line(sprintf('Track edges:                %d', $this->networkCount($repository, 'TRACK')));
        $this->line(sprintf('Rail edges:                 %d', $this->networkCount($repository, 'RAIL')));
        $this->line(sprintf('Pipeline edges:             %d', $this->networkCount($repository, 'PIPELINE')));
        $this->line(sprintf('Escarpment edges:           %d', $this->networkCount($repository, 'ESCARPMENT')));
        $locations = $repository->read('map/locations.json')['locations'] ?? [];
        $this->line(sprintf('Locations:                  %d', count($locations)));
        $this->line(sprintf('Locations resolved:         %d', count(array_filter($locations, fn (array $row): bool => ($row['status'] ?? null) === 'RESOLVED'))));
        $this->line(sprintf('Locations unresolved:       %d', count(array_filter($locations, fn (array $row): bool => ($row['status'] ?? null) !== 'RESOLVED'))));
        $this->line(sprintf('Areas:                      %d', $count('areas', 'areas')));
        $this->line(sprintf('Facilities linked:          %d', count(array_filter($locations, fn (array $row): bool => ($row['status'] ?? null) === 'RESOLVED'))));
        $this->line(sprintf('Not reviewed:               %d', count(array_filter($hexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'NOT_REVIEWED'))));
        $this->line('By section:');
        foreach (['Map A', 'Map B', 'Map C', 'Map D', 'Map E', 'Malta'] as $section) {
            $sectionHexes = array_values(array_filter($hexes, fn (array $row): bool => ($row['map_section'] ?? null) === $section));
            $this->line(sprintf('  %-6s hexes=%4d resolved=%4d ambiguous=%4d unresolved=%4d not_reviewed=%4d',
                $section,
                count($sectionHexes),
                count(array_filter($sectionHexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'RESOLVED')),
                count(array_filter($sectionHexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'AMBIGUOUS')),
                count(array_filter($sectionHexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'UNRESOLVED_REVIEWED')),
                count(array_filter($sectionHexes, fn (array $row): bool => ($row['terrain_status'] ?? null) === 'NOT_REVIEWED')),
            ));
        }

        return self::SUCCESS;
    }

    private function networkCount(CanonicalDataRepository $repository, string $type): int
    {
        return count(array_filter($repository->read('map/linear-networks.json')['linear-networks'] ?? [], fn (array $row): bool => ($row['network_type'] ?? null) === $type));
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function index(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (isset($row['unit_id']) && is_string($row['unit_id'])) {
                $indexed[$row['unit_id']] = $row;
            }
        }

        return $indexed;
    }

    /** @param array<int, string> $ids
     * @param  array<string, array<string, mixed>>  $mapping
     */
    private function countMatching(array $ids, array $mapping, callable $predicate): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if (isset($mapping[$id]) && $predicate($mapping[$id])) {
                $count++;
            }
        }

        return $count;
    }
}
