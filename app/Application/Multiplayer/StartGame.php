<?php

namespace App\Application\Multiplayer;

use App\Application\Scenario\ScenarioInitializer;
use App\Domain\Multiplayer\EventVisibility;
use App\Domain\Multiplayer\GameStatus;
use App\Domain\Multiplayer\RoleCode;
use App\Models\Game;
use App\Models\PendingDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StartGame
{
    public function __construct(
        private readonly ScenarioInitializer $initializer,
        private readonly GameEventRecorder $events,
        private readonly RevisionGuard $revisions,
    ) {}

    public function handle(Game $game, int $expectedRevision): Game
    {
        return DB::transaction(function () use ($game, $expectedRevision): Game {
            $locked = Game::query()->lockForUpdate()->with(['scenario', 'teams.memberships.roles'])->findOrFail($game->id);
            $this->revisions->assert($locked, $expectedRevision);
            if ($locked->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['game' => 'Only a lobby can start.']);
            }

            foreach ($locked->teams as $team) {
                $commanders = $team->memberships->filter(fn ($member) => $member->roles->contains('code', RoleCode::CommanderInChief->value));
                if ($commanders->isEmpty()) {
                    throw ValidationException::withMessages(['roles' => "{$team->code->value} requires a Commander-in-Chief."]);
                }
            }

            $this->initializer->initialize($locked, $locked->scenario);
            $locked->status = GameStatus::Active;
            $locked->started_at = now();

            foreach ($locked->teams as $team) {
                $decision = PendingDecision::create([
                    'game_id' => $locked->id,
                    'team_id' => $team->id,
                    'type' => 'INITIAL_COMMAND',
                    'required_role_code' => RoleCode::CommanderInChief,
                    'visibility' => EventVisibility::PrivateTeam,
                    'public_context' => ['summary' => 'Initial command acknowledgement required'],
                ]);
                $participants = $team->memberships
                    ->filter(fn ($member) => $member->roles->contains('code', RoleCode::CommanderInChief->value))
                    ->pluck('id');
                $decision->participants()->attach($participants);
            }

            $this->events->record($locked, 'GameStarted', $locked->creator, [
                'turn' => $locked->scenario->initial_turn,
                'operation_stage' => $locked->scenario->initial_operation_stage,
            ]);
            $this->revisions->bump($locked);

            return $locked->fresh(['scenario', 'teams', 'memberships.user', 'memberships.team', 'memberships.roles', 'turnState', 'decisions.team', 'decisions.participants.user']);
        });
    }
}
