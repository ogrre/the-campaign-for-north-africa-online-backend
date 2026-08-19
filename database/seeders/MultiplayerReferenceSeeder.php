<?php

namespace Database\Seeders;

use App\Domain\Multiplayer\RoleCode;
use App\Models\GameRoleDefinition;
use App\Models\ScenarioDefinition;
use Illuminate\Database\Seeder;

class MultiplayerReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleCode::cases() as $role) {
            GameRoleDefinition::updateOrCreate(['code' => $role->value], [
                'name' => ucwords(strtolower(str_replace('_', ' ', $role->value))),
                'required_to_start' => $role === RoleCode::CommanderInChief,
            ]);
        }

        $scenarios = [
            ['slug' => 'the-italians', 'name' => 'The Italians', 'initial_turn' => 1, 'initial_operation_stage' => 1, 'source_reference' => 'Rules_Scenarios_07-2021.pdf §60.0–60.23'],
            ['slug' => 'the-desert-fox', 'name' => 'The Desert Fox', 'initial_turn' => 26, 'initial_operation_stage' => 3, 'source_reference' => 'Rules_Scenarios_07-2021.pdf §61.0–61.2'],
            ['slug' => 'operation-crusader', 'name' => 'Operation Crusader', 'initial_turn' => 57, 'initial_operation_stage' => 3, 'source_reference' => 'Rules_Scenarios_07-2021.pdf §62.0–62.2'],
        ];

        foreach ($scenarios as $scenario) {
            ScenarioDefinition::updateOrCreate(['slug' => $scenario['slug']], [
                ...$scenario,
                'source_document' => 'CNAv2.1.0/Rules_Scenarios_07-2021.pdf',
                'setup_complete' => false,
            ]);
        }
    }
}
