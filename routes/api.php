<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameEventController;
use App\Http\Controllers\GameOrderOfBattleController;
use App\Http\Controllers\GamePlayerController;
use App\Http\Controllers\GameStateController;
use App\Http\Controllers\GameStatusController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\MapReferenceController;
use App\Http\Controllers\MapReviewController;
use App\Http\Controllers\ScenarioController;
use App\Http\Middleware\MapReviewAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::middleware('throttle:auth')->prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', fn (Request $request) => response()->json(['data' => $request->user()]));
    Route::get('/scenarios', ScenarioController::class);
    Route::get('/reference/maps', [MapReferenceController::class, 'index']);
    Route::get('/reference/maps/main_map/hexes/{hex}', [MapReferenceController::class, 'hex']);
    Route::get('/reference/maps/main_map/hexes/{hex}/neighbors', [MapReferenceController::class, 'neighbors']);
    Route::middleware(MapReviewAccess::class)->prefix('dev/map-review')->group(function (): void {
        Route::get('/regions', [MapReviewController::class, 'regions']);
        Route::get('/regions/{region}', [MapReviewController::class, 'region']);
        Route::get('/hexes/{hex}', [MapReviewController::class, 'hex']);
        Route::post('/sessions', [MapReviewController::class, 'startSession']);
        Route::post('/decisions', [MapReviewController::class, 'decision']);
        Route::post('/decisions/bulk', [MapReviewController::class, 'bulk']);
        Route::get('/progress', [MapReviewController::class, 'progress']);
        Route::get('/conflicts', [MapReviewController::class, 'conflicts']);
        Route::get('/export', [MapReviewController::class, 'export']);
    });

    Route::apiResource('games', GameController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/games/{game}/join', [LobbyController::class, 'join']);
    Route::post('/games/{game}/leave', [LobbyController::class, 'leave']);
    Route::post('/games/{game}/start', [LobbyController::class, 'start']);
    Route::post('/games/{game}/pause', [GameStateController::class, 'pause']);
    Route::post('/games/{game}/resume', [GameStateController::class, 'resume']);
    Route::get('/games/{game}/players', [GamePlayerController::class, 'index']);
    Route::patch('/games/{game}/players/{player}', [GamePlayerController::class, 'update']);
    Route::delete('/games/{game}/players/{player}', [GamePlayerController::class, 'destroy']);
    Route::post('/games/{game}/players/{player}/roles', [GamePlayerController::class, 'addRole']);
    Route::delete('/games/{game}/players/{player}/roles/{role}', [GamePlayerController::class, 'removeRole']);
    Route::post('/games/{game}/invites', [InvitationController::class, 'store']);
    Route::post('/invites/{token}/accept', [InvitationController::class, 'accept']);
    Route::post('/invites/{token}/decline', [InvitationController::class, 'decline']);
    Route::get('/games/{game}/events', GameEventController::class);
    Route::get('/games/{game}/order-of-battle', GameOrderOfBattleController::class);
    Route::get('/games/{game}/status', GameStatusController::class);
});
