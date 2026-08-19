<?php

namespace App\Domain\Multiplayer;

use RuntimeException;

final class StaleGameRevision extends RuntimeException
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct('The game state changed. Refresh and retry.');
    }
}
