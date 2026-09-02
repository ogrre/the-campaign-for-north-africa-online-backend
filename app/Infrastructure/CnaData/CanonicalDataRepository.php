<?php

namespace App\Infrastructure\CnaData;

use RuntimeException;

final class CanonicalDataRepository
{
    public function __construct(private readonly ?string $directory = null) {}

    /** @return array<string, mixed> */
    public function read(string $relativePath): array
    {
        $path = ($this->directory ?? resource_path('cna')).'/'.$relativePath;
        if (! is_file($path)) {
            throw new RuntimeException("Canonical CNA data file not found: {$relativePath}");
        }
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Canonical CNA data root must be an object: {$relativePath}");
        }

        return $decoded;
    }
}
