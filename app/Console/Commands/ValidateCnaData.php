<?php

namespace App\Console\Commands;

use App\Infrastructure\CnaData\CanonicalDataRepository;
use App\Infrastructure\CnaData\CanonicalDataValidator;
use Illuminate\Console\Command;

final class ValidateCnaData extends Command
{
    protected $signature = 'cna:validate-data';

    protected $description = 'Validate canonical CNA files and logical references';

    public function handle(CanonicalDataRepository $repository, CanonicalDataValidator $validator): int
    {
        $counts = $validator->validate($repository);
        $this->info("Canonical data valid: {$counts['units']} units, {$counts['formations']} formations, {$counts['equipment']} equipment, {$counts['characteristics']} characteristics, {$counts['trucks']} truck characteristics, {$counts['setup_units']} setup units.");
        $this->warn("TOE states: {$counts['toe_total']} explicit definitions ({$counts['toe_definitions']} complete, {$counts['toe_partial']} partial); {$counts['toe_exceptions']} unresolved review records retained explicitly.");

        return self::SUCCESS;
    }
}
