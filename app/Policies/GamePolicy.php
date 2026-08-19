<?php

namespace App\Policies;

use App\Domain\Multiplayer\GameStatus;
use App\Domain\Multiplayer\MembershipPrivilege;
use App\Models\Game;
use App\Models\User;

final class GamePolicy
{
    public function view(User $user, Game $game): bool
    {
        return $game->creator_id === $user->id || $game->memberships()->where('user_id', $user->id)->where('status', 'ACTIVE')->exists();
    }

    public function updateLobby(User $user, Game $game): bool
    {
        return $this->managesLobby($user, $game);
    }

    public function invitePlayer(User $user, Game $game): bool
    {
        return $this->managesLobby($user, $game);
    }

    public function assignRole(User $user, Game $game): bool
    {
        return $this->managesLobby($user, $game);
    }

    public function start(User $user, Game $game): bool
    {
        return $this->managesLobby($user, $game);
    }

    public function pause(User $user, Game $game): bool
    {
        return $game->creator_id === $user->id;
    }

    public function leave(User $user, Game $game): bool
    {
        return $game->status === GameStatus::Lobby && $game->creator_id !== $user->id && $this->view($user, $game);
    }

    private function managesLobby(User $user, Game $game): bool
    {
        if ($game->status !== GameStatus::Lobby) {
            return false;
        }
        if ($game->creator_id === $user->id) {
            return true;
        }

        return $game->memberships()->where('user_id', $user->id)->where('privilege', MembershipPrivilege::Gamemaster)->exists();
    }
}
