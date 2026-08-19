<?php

namespace App\Http\Controllers;

use App\Application\Multiplayer\AcceptInvitation;
use App\Application\Multiplayer\CreateInvitation;
use App\Http\Requests\Game\AcceptInviteRequest;
use App\Http\Requests\Game\InviteRequest;
use App\Http\Resources\MembershipResource;
use App\Models\Game;
use App\Models\GameInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InvitationController extends Controller
{
    public function store(InviteRequest $request, Game $game, CreateInvitation $command): JsonResponse
    {
        $this->authorize('invitePlayer', $game);
        [$invitation, $token] = $command->handle($game, $request->user(), $request->validated());

        return response()->json(['data' => ['id' => $invitation->id, 'token' => $token, 'expires_at' => $invitation->expires_at]], 201);
    }

    public function accept(AcceptInviteRequest $request, string $token, AcceptInvitation $command): JsonResponse
    {
        $membership = $command->handle($token, $request->user(), $request->validated('team'));

        return response()->json(['data' => [
            'game_id' => $membership->game_id,
            'membership' => new MembershipResource($membership->load(['user', 'team', 'roles'])),
        ]], 201);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $invite = GameInvitation::where('token_hash', hash('sha256', $token))->first();
        if (! $invite || $invite->accepted_at || $invite->declined_at || $invite->expires_at->isPast()) {
            throw ValidationException::withMessages(['token' => 'Invitation is invalid or expired.']);
        }
        if ($invite->invited_email && strcasecmp($invite->invited_email, $request->user()->email) !== 0) {
            throw ValidationException::withMessages(['token' => 'This invitation belongs to another email address.']);
        }
        $invite->update(['declined_at' => now()]);

        return response()->json(['message' => 'Invitation declined.']);
    }
}
