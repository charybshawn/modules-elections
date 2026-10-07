<?php

namespace Cultpantry\Elections\Console;

use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports research XML files from the command line -- the same import as
 * Admin → Elections → Import XML, so research passes can be loaded without
 * going through the browser. --dry-run runs the whole import and rolls it
 * back, reporting what would change.
 */
class ImportElectionCommand extends Command
{
    protected $signature = 'elections:import
        {files* : One or more election XML files, imported in the order given}
        {--dry-run : Run the import and report the result, then roll everything back}';

    protected $description = 'Import election research XML (the same format as Admin → Elections → Import XML)';

    public function handle(ImportElectionFromXml $import): int
    {
        $failed = false;

        foreach ($this->argument('files') as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                $this->error("{$path}: not a readable file.");
                $failed = true;

                continue;
            }

            $this->line('<options=bold>'.basename($path).'</>'.($this->option('dry-run') ? ' <fg=yellow>(dry run)</>' : ''));

            DB::beginTransaction();
            try {
                $result = $import->handleString((string) file_get_contents($path), basename($path));
            } catch (RuntimeException $e) {
                DB::rollBack();
                $this->error('  '.$e->getMessage());
                $failed = true;

                continue;
            }
            $this->option('dry-run') ? DB::rollBack() : DB::commit();

            // The summary already lists the first few problems; print them all, one per line.
            $this->line('  '.$import->summarize([...$result, 'problems' => []]));
            foreach ($result['problems'] as $problem) {
                $this->warn('  ! '.$problem);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
