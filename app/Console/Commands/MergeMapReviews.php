<?php

namespace App\Console\Commands;

use App\Models\MapReviewDecision;
use Illuminate\Console\Command;

final class MergeMapReviews extends Command
{
    protected $signature = 'cna:merge-map-reviews';

    protected $description = 'Merge only CONFIRMED human map decisions into canonical map references';

    public function handle(): int
    {
        $rows = MapReviewDecision::query()->where('decision', 'CONFIRMED')->get();
        /** @var array<string, array<int, MapReviewDecision>> $groups */
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row->entity_type.'|'.$row->entity_id][] = $row;
        }
        foreach ($groups as $key => $group) {
            $values = array_unique(array_map(fn (MapReviewDecision $row): string => json_encode($row->value), $group));
            if (count($values) > 1) {
                $this->error('Conflict prevents merge: '.$key);

                return self::FAILURE;
            }
        }
        $root = base_path('../data/canonical/map');
        $hexPath = is_file($root.'/hexes.json') ? $root.'/hexes.json' : resource_path('cna/map/hexes.json');
        $hexes = json_decode(file_get_contents($hexPath), true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, array<string, mixed>> $byId */
        $byId = [];
        foreach ($hexes['hexes'] as $hex) {
            $byId[$hex['id']] = $hex;
        }
        foreach ($rows as $row) {
            $value = $row->getAttribute('value');
            if ($row->entity_type === 'hex' && is_array($value) && isset($value['terrain_id'])) {
                abort_unless(isset($byId[$row->entity_id]), 422, 'Unknown hex in review merge.');
                $hex = $byId[$row->entity_id];
                $hex['terrain_id'] = $value['terrain_id'];
                $hex['terrain_status'] = 'RESOLVED';
                $hex['provenance'][] = ['method' => 'human map review', 'reviewer_id' => $row->reviewer_id, 'reviewed_at' => $row->updated_at?->toIso8601String(), 'region_id' => $row->region_id, 'source' => $row->source];
                $byId[$row->entity_id] = $hex;
            }
        }
        $hexes['hexes'] = array_values($byId);
        file_put_contents($hexPath, json_encode($hexes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        if ($hexPath !== resource_path('cna/map/hexes.json')) {
            copy($hexPath, resource_path('cna/map/hexes.json'));
        }

        $canonicalRoot = is_dir($root) ? $root : resource_path('cna/map');
        $hexsidePath = $canonicalRoot.'/hexsides.json';
        $networkPath = $canonicalRoot.'/linear-networks.json';
        $hexsides = is_file($hexsidePath) ? json_decode(file_get_contents($hexsidePath), true, 512, JSON_THROW_ON_ERROR) : ['hexsides' => []];
        $networks = is_file($networkPath) ? json_decode(file_get_contents($networkPath), true, 512, JSON_THROW_ON_ERROR) : ['linear-networks' => []];
        $hexsideIndex = [];
        foreach ($hexsides['hexsides'] as $index => $side) {
            $hexsideIndex[$side['id']] = $index;
        }
        $networkIndex = [];
        foreach ($networks['linear-networks'] as $index => $edge) {
            $networkIndex[($edge['network_type'] ?? '').':'.($edge['hex_a'] ?? '').'--'.($edge['hex_b'] ?? '')] = $index;
        }
        foreach ($rows as $row) {
            $value = $row->getAttribute('value');
            if (! is_array($value)) {
                continue;
            }
            $provenance = [['method' => 'human map review', 'reviewer_id' => $row->reviewer_id, 'reviewed_at' => $row->updated_at?->toIso8601String(), 'region_id' => $row->region_id, 'source' => $row->source]];
            if ($row->entity_type === 'hexside' && isset($hexsideIndex[$row->entity_id])) {
                $index = $hexsideIndex[$row->entity_id];
                $hexsides['hexsides'][$index]['features'][] = $value;
                $hexsides['hexsides'][$index]['status'] = 'RESOLVED';
                $hexsides['hexsides'][$index]['provenance'] = array_merge($hexsides['hexsides'][$index]['provenance'] ?? [], $provenance);
            }
            if ($row->entity_type === 'network' && isset($value['hex_a'], $value['hex_b'], $value['network_type'])) {
                $key = $value['network_type'].':'.$value['hex_a'].'--'.$value['hex_b'];
                $edge = ['id' => $key, 'network_type' => $value['network_type'], 'hex_a' => $value['hex_a'], 'hex_b' => $value['hex_b'], 'properties' => $value['properties'] ?? [], 'status' => 'RESOLVED', 'provenance' => $provenance];
                if (isset($networkIndex[$key])) {
                    $networks['linear-networks'][$networkIndex[$key]] = $edge;
                } else {
                    $networkIndex[$key] = count($networks['linear-networks']);
                    $networks['linear-networks'][] = $edge;
                }
            }
        }
        file_put_contents($hexsidePath, json_encode($hexsides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        file_put_contents($networkPath, json_encode($networks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        if ($canonicalRoot !== resource_path('cna/map')) {
            copy($hexsidePath, resource_path('cna/map/hexsides.json'));
            copy($networkPath, resource_path('cna/map/linear-networks.json'));
        }
        $this->info('Merged '.$rows->count().' confirmed decisions; conflicts: 0.');

        return self::SUCCESS;
    }
}
