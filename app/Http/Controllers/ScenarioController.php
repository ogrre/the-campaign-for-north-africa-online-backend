<?php

namespace App\Http\Controllers;

use App\Models\ScenarioDefinition;
use Illuminate\Http\JsonResponse;

final class ScenarioController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => ScenarioDefinition::query()->orderBy('name')->get()]);
    }
}
