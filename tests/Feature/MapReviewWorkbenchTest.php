<?php

namespace Tests\Feature;

use App\Infrastructure\CnaData\CanonicalDataImporter;
use App\Models\User;
use Database\Seeders\MultiplayerReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MapReviewWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MultiplayerReferenceSeeder::class);
        app(CanonicalDataImporter::class)->import();
        config(['cna.map_review_enabled' => true]);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_regions_and_decisions_are_separate_from_canonical_data(): void
    {
        $this->getJson('/api/dev/map-review/regions')->assertOk()->assertJsonPath('data.regions.0.map_section', 'Map A');
        $this->getJson('/api/dev/map-review/regions/map-a-col-18')->assertOk()->assertJsonStructure(['data' => ['locations', 'hexes', 'terrain_definitions', 'terrain_visual_reference']])->assertJsonPath('data.terrain_visual_reference.0.terrain_key', 'clear');
        $this->postJson('/api/dev/map-review/decisions', [
            'map_section' => 'Map A', 'region_id' => 'map-a-col-18', 'entity_type' => 'hex',
            'entity_id' => 'A1800', 'decision' => 'CONFIRMED', 'value' => ['terrain_id' => 'rock_gravel'],
        ])->assertCreated()->assertJsonPath('data.decision', 'CONFIRMED');
        $this->getJson('/api/dev/map-review/progress')->assertOk()->assertJsonPath('data.confirmed', 1);
    }

    public function test_invalid_terrain_and_non_neighbor_network_are_rejected(): void
    {
        $this->postJson('/api/dev/map-review/decisions', [
            'map_section' => 'Map A', 'entity_type' => 'hex', 'entity_id' => 'A1800', 'decision' => 'CONFIRMED', 'value' => ['terrain_id' => 'invented'],
        ])->assertStatus(422);
        $this->postJson('/api/dev/map-review/decisions', [
            'map_section' => 'Map A', 'entity_type' => 'network', 'entity_id' => 'A1800--A1802', 'decision' => 'CONFIRMED',
            'value' => ['hex_a' => 'A1800', 'hex_b' => 'A1802', 'network_type' => 'ROAD'],
        ])->assertStatus(422);
    }

    public function test_conflicting_review_values_are_exposed(): void
    {
        foreach (['rock_gravel', 'sea'] as $terrain) {
            $this->postJson('/api/dev/map-review/decisions', [
                'map_section' => 'Map A', 'entity_type' => 'hex', 'entity_id' => 'A1800', 'decision' => 'CONFIRMED', 'value' => ['terrain_id' => $terrain],
            ])->assertCreated();
        }
        $this->getJson('/api/dev/map-review/conflicts')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_workbench_is_disabled_when_feature_flag_is_off(): void
    {
        config(['cna.map_review_enabled' => false]);
        $this->getJson('/api/dev/map-review/regions')->assertNotFound();
    }

    public function test_ambiguous_decisions_are_not_merged(): void
    {
        $this->postJson('/api/dev/map-review/decisions', [
            'map_section' => 'Map A', 'entity_type' => 'hex', 'entity_id' => 'A1800', 'decision' => 'AMBIGUOUS', 'value' => ['terrain_id' => 'rock_gravel'],
        ])->assertCreated();
        $this->artisan('cna:merge-map-reviews')->assertExitCode(0)->expectsOutputToContain('Merged 0 confirmed decisions');
    }

    public function test_bulk_decisions_are_individual_and_transactional(): void
    {
        $payload = [
            'decisions' => [
                ['map_section' => 'Map A', 'region_id' => 'map-a-col-18', 'entity_type' => 'hex', 'entity_id' => 'A1800', 'decision' => 'CONFIRMED', 'value' => ['terrain_id' => 'clear']],
                ['map_section' => 'Map A', 'region_id' => 'map-a-col-18', 'entity_type' => 'hex', 'entity_id' => 'A1801', 'decision' => 'CONFIRMED', 'value' => ['terrain_id' => 'clear']],
            ],
        ];
        $this->postJson('/api/dev/map-review/decisions/bulk', $payload)->assertCreated()->assertJsonPath('data.count', 2);
        $this->assertDatabaseCount('map_review_decisions', 2);

        $conflicting = $payload;
        $conflicting['decisions'][1]['value'] = ['terrain_id' => 'sea'];
        $this->postJson('/api/dev/map-review/decisions/bulk', $conflicting)->assertStatus(422);
        $this->assertDatabaseCount('map_review_decisions', 2);
    }
}
