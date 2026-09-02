<?php

namespace Tests\Feature;

use App\Infrastructure\CnaData\CanonicalDataImporter;
use App\Models\User;
use Database\Seeders\MultiplayerReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MapReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MultiplayerReferenceSeeder::class);
        app(CanonicalDataImporter::class)->import();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_map_reference_lists_catalog_and_returns_hex_neighbors(): void
    {
        $this->getJson('/api/reference/maps')->assertOk()->assertJsonPath('data.coverage.hexes', 7083);
        $this->getJson('/api/reference/maps/main_map/hexes/C4218')->assertOk()->assertJsonPath('data.id', 'C4218');
        $this->getJson('/api/reference/maps/main_map/hexes/C4218/neighbors')->assertOk()->assertJsonPath('data.hex', 'C4218');
    }
}
