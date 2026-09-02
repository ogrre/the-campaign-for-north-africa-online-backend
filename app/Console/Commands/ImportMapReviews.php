<?php

namespace App\Console\Commands;

use App\Models\MapReviewDecision;
use Illuminate\Console\Command;

final class ImportMapReviews extends Command
{
    protected $signature = 'cna:import-map-reviews {file} {--reviewer=1}';

    protected $description = 'Import versioned human map review decisions';

    public function handle(): int
    {
        $payload = json_decode(file_get_contents($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($payload['decisions'] ?? [] as $row) {
            unset($row['id'], $row['created_at'], $row['updated_at']);
            $row['reviewer_id'] ??= (int) $this->option('reviewer');
            MapReviewDecision::create($row);
        }
        $this->info('Imported '.count($payload['decisions'] ?? []).' map review decisions.');

        return self::SUCCESS;
    }
}
