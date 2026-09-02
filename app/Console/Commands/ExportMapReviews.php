<?php

namespace App\Console\Commands;

use App\Models\MapReviewDecision;
use Illuminate\Console\Command;

final class ExportMapReviews extends Command
{
    protected $signature = 'cna:export-map-reviews {--output=}';

    protected $description = 'Export human map review decisions as versionable JSON';

    public function handle(): int
    {
        $path = $this->option('output') ?: base_path('../data/review/map/human-decisions/export.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode(['schema_version' => 1, 'exported_at' => now()->toIso8601String(), 'decisions' => MapReviewDecision::all()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->info('Exported '.MapReviewDecision::count().' map review decisions to '.$path);

        return self::SUCCESS;
    }
}
