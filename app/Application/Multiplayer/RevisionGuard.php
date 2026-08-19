<?php

namespace App\Application\Multiplayer;

use App\Domain\Multiplayer\StaleGameRevision;
use App\Models\Game;

final class RevisionGuard
{
    public function assert(Game $game, int $expected): void
    {
        if ($game->revision !== $expected) {
            throw new StaleGameRevision($game->revision);
        }
    }

    public function bump(Game $game): void
    {
        $game->revision++;
        $game->save();
    }
}
