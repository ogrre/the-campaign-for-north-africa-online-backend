<?php

namespace App\Application\Multiplayer;

use App\Domain\Multiplayer\EventVisibility;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\User;

final class GameEventRecorder
{
    /** @param array<string, mixed> $payload */
    public function record(
        Game $game,
        string $type,
        ?User $actor = null,
        array $payload = [],
        EventVisibility $visibility = EventVisibility::Public,
        ?string $teamId = null,
    ): GameEvent {
        $game->event_sequence++;
        $game->save();

        return GameEvent::create([
            'game_id' => $game->id,
            'sequence' => $game->event_sequence,
            'type' => $type,
            'actor_user_id' => $actor?->id,
            'team_id' => $teamId,
            'visibility' => $visibility,
            'payload' => $payload,
        ]);
    }
}
