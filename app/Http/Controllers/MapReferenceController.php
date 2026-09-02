<?php

namespace App\Http\Controllers;

use App\Infrastructure\CnaData\CanonicalDataRepository;
use Illuminate\Http\JsonResponse;

final class MapReferenceController
{
    public function index(CanonicalDataRepository $repository): JsonResponse
    {
        return response()->json([
            'data' => [
                'maps' => $repository->read('map/maps.json')['maps'] ?? [],
                'boards' => $repository->read('map/boards.json')['boards'] ?? [],
                'coverage' => ['hexes' => count($repository->read('map/hexes.json')['hexes'] ?? [])],
            ],
        ]);
    }

    public function hex(string $hex, CanonicalDataRepository $repository): JsonResponse
    {
        $rows = $repository->read('map/hexes.json')['hexes'] ?? [];
        foreach ($rows as $row) {
            if ($row['id'] === $hex) {
                return response()->json(['data' => $row]);
            }
        }

        return response()->json(['message' => 'Hex not found.'], 404);
    }

    public function neighbors(string $hex, CanonicalDataRepository $repository): JsonResponse
    {
        $rows = $repository->read('map/hexes.json')['hexes'] ?? [];
        $row = null;
        foreach ($rows as $candidate) {
            if (($candidate['id'] ?? null) === $hex) {
                $row = $candidate;
                break;
            }
        }
        if (! $row) {
            return response()->json(['message' => 'Hex not found.'], 404);
        }
        $indexed = [];
        foreach ($rows as $candidate) {
            if (isset($candidate['id'])) {
                $indexed[$candidate['id']] = $candidate;
            }
        }
        $neighbors = [];
        foreach ($row['neighbors'] ?? [] as $direction => $id) {
            $neighbors[] = ['direction' => $direction, 'hex' => $indexed[$id] ?? null];
        }

        return response()->json(['data' => ['hex' => $hex, 'neighbors' => $neighbors]]);
    }
}
