<?php

namespace App\Application\Multiplayer;

use App\Domain\Multiplayer\GameStatus;
use App\Domain\Multiplayer\MembershipPrivilege;
use App\Domain\Multiplayer\TeamCode;
use App\Models\Game;
use App\Models\GameMembership;
use App\Models\GameTeam;
use App\Models\ScenarioDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateGame
{
    public function __construct(private readonly GameEventRecorder $events) {}

    public function handle(User $creator, ScenarioDefinition $scenario, string $name, TeamCode $teamCode): Game
    {
        return DB::transaction(function () use ($creator, $scenario, $name, $teamCode): Game {
            $game = Game::create([
                'name' => $name,
                'creator_id' => $creator->id,
                'scenario_definition_id' => $scenario->id,
                'status' => GameStatus::Lobby,
                'ruleset' => 'cna-2021-integrated-errata',
                'revision' => 1,
                'event_sequence' => 0,
            ]);

            foreach (TeamCode::cases() as $code) {
                GameTeam::create(['game_id' => $game->id, 'code' => $code, 'name' => ucfirst(strtolower($code->value))]);
            }

            $team = $game->teams()->where('code', $teamCode)->firstOrFail();
            GameMembership::create([
                'game_id' => $game->id,
                'user_id' => $creator->id,
                'team_id' => $team->id,
                'privilege' => MembershipPrivilege::Player,
            ]);

            $this->events->record($game, 'GameCreated', $creator, ['name' => $name, 'scenario' => $scenario->slug]);
            $this->events->record($game, 'PlayerJoinedGame', $creator, ['user_id' => $creator->id, 'team' => $teamCode->value]);

            return $game->load(['scenario', 'teams', 'memberships.user', 'memberships.team', 'memberships.roles']);
        });
    }
}
