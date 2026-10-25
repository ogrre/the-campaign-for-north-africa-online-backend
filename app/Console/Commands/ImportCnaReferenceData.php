<?php

namespace App\Console\Commands;

use App\Infrastructure\CnaData\CanonicalDataImporter;
use Illuminate\Console\Command;

final class ImportCnaReferenceData extends Command
{
    protected $signature = 'cna:import-reference-data';

    protected $description = 'Transactionally import the versioned canonical CNA dataset';

    public function handle(CanonicalDataImporter $importer): int
    {
        $counts = $importer->import();
        $this->info("Imported {$counts['units']} units, {$counts['formations']} formations, {$counts['equipment']} equipment, {$counts['characteristics']} characteristics and {$counts['setup_units']} setup units.");

        return self::SUCCESS;
    }
}
