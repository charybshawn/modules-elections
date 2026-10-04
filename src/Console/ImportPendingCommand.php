<?php

namespace Cultpantry\Elections\Console;

use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\ImportRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Imports the research files in the host app's research folder
 * (config elections.import_path, default database/elections) that aren't in
 * the ledger yet, oldest name first. Each file is all-or-nothing, and the
 * run stops at the first failure so nothing later lands on top of it. The
 * ledger is keyed by content hash: the files themselves are never moved, so
 * a deploy's working tree stays clean, and editing a file makes it pending
 * again. Runs on deploy and hourly (see the service provider).
 */
class ImportPendingCommand extends Command
{
    protected $signature = 'elections:import-pending
        {--dry-run : Import the pending files, report, then roll everything back (nothing is recorded)}
        {--mark-only : Record the pending files as imported without importing them (to adopt files already loaded another way)}';

    protected $description = 'Import research XML files that have not been imported yet, in filename order';

    public function handle(ImportElectionFromXml $import): int
    {
        $dir = static::directory();
        if (! is_dir($dir)) {
            $this->line("No research folder at {$dir}; nothing to do.");

            return self::SUCCESS;
        }

        $files = glob($dir.'/*.xml') ?: [];
        sort($files, SORT_STRING);

        $done = ImportRecord::pluck('hash')->flip();
        $pending = array_values(array_filter($files, fn ($path) => ! $done->has(hash_file('sha256', $path))));

        if ($pending === []) {
            $this->line('No pending research files.');

            return self::SUCCESS;
        }

        // A dry run imports every pending file inside one outer transaction,
        // so later files can see earlier ones, then rolls the lot back.
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            DB::beginTransaction();
        }

        foreach ($pending as $path) {
            $name = basename($path);
            $hash = hash_file('sha256', $path);

            if ($this->option('mark-only')) {
                ImportRecord::create([
                    'filename' => $name, 'hash' => $hash, 'summary' => 'Marked as imported without importing.',
                    'marked_only' => true, 'imported_at' => now(),
                ]);
                $this->line("<options=bold>{$name}</> marked as imported.");

                continue;
            }

            $this->line('<options=bold>'.$name.'</>'.($dryRun ? ' <fg=yellow>(dry run)</>' : ''));

            DB::beginTransaction();
            try {
                $result = $import->handleString((string) file_get_contents($path));
                if (! $dryRun) {
                    ImportRecord::create([
                        'filename' => $name,
                        'hash' => $hash,
                        'summary' => $import->summarize([...$result, 'problems' => []]),
                        'problems' => $result['problems'] ?: null,
                        'imported_at' => now(),
                    ]);
                }
            } catch (RuntimeException $e) {
                DB::rollBack();
                $this->error('  '.$e->getMessage());
                $this->error('  Stopped: later files were not imported.');
                if ($dryRun) {
                    DB::rollBack();
                } else {
                    report($e);
                }

                return self::FAILURE;
            } catch (Throwable $e) {
                DB::rollBack();
                if ($dryRun) {
                    DB::rollBack();
                }
                throw $e;
            }
            DB::commit();

            $this->line('  '.$import->summarize([...$result, 'problems' => []]));
            foreach ($result['problems'] as $problem) {
                $this->warn('  ! '.$problem);
            }
        }

        if ($dryRun) {
            DB::rollBack();
        }

        return self::SUCCESS;
    }

    public static function directory(): string
    {
        return rtrim((string) config('elections.import_path', database_path('elections')), '/');
    }
}
