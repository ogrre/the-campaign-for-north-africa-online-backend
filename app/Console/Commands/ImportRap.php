<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class ImportRap extends Command
{
    protected $signature = 'dataset:import-rap {year=2024} {--download-only} {--parse-only} {--program=} {--force}';

    protected $description = 'Download and parse official RAP PDFs for a budget year';

    public function handle(): int
    {
        $year = (string) $this->argument('year');
        if ($year !== '2024') {
            $this->error('Only RAP 2024 is currently implemented.');

            return self::INVALID;
        }

        $root = dirname(__DIR__, 3);
        $python = is_file($root.'/.venv-terrain/bin/python') ? $root.'/.venv-terrain/bin/python' : 'python3';
        $script = $root.'/../tools/import_rap_2024.py';
        $arguments = [$python, $script];
        foreach (['download-only' => '--download-only', 'parse-only' => '--parse-only', 'force' => '--force'] as $option => $flag) {
            if ($this->option($option)) {
                $arguments[] = $flag;
            }
        }
        if ($program = $this->option('program')) {
            $arguments[] = '--program='.$program;
        }

        $process = new Process($arguments, $root);
        $process->setTimeout(null);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));
        if (! $process->isSuccessful()) {
            $this->error('RAP import failed: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
