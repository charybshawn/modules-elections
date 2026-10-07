<?php

use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\ImportRecord;
use Cultpantry\Elections\Models\Plank;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

function researchDir(array $files): string
{
    $dir = sys_get_temp_dir().'/elections-research-'.uniqid();
    File::ensureDirectoryExists($dir.'/archive');
    foreach ($files as $name => $xml) {
        file_put_contents("{$dir}/{$name}", $xml);
    }
    config(['elections.import_path' => $dir]);

    return $dir;
}

function candidateXml(string $name, string $office = 'councillor'): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><election><candidates>'
        ."<candidate><name>{$name}</name><office>{$office}</office><status>nominated</status></candidate>"
        .'</candidates></election>';
}

describe('elections:import-pending', function () {
    it('imports pending files in name order, records them, and skips them next time', function () {
        $dir = researchDir([
            '2026-10-03-02-research.xml' => '<?xml version="1.0" encoding="UTF-8"?><election><candidates><candidate><name>Jane Example</name>'
                .'<entries><entry><kind>statement</kind><topic>housing</topic><summary>More homes.</summary>'
                .'<source_url>https://example.com/a</source_url><source_type>news</source_type></entry></entries></candidate></candidates></election>',
            '2026-10-03-01-roster.xml' => candidateXml('Jane Example'),
        ]);
        file_put_contents($dir.'/archive/old.xml', candidateXml('Archived Person'));

        $this->artisan('elections:import-pending')->assertSuccessful();

        expect(Entry::count())->toBe(1)
            ->and(Candidate::where('name', 'Archived Person')->exists())->toBeFalse()
            ->and(ImportRecord::orderBy('id')->pluck('filename')->all())->toBe(['2026-10-03-01-roster.xml', '2026-10-03-02-research.xml']);

        $this->artisan('elections:import-pending')->expectsOutputToContain('No pending research files.')->assertSuccessful();
        expect(ImportRecord::count())->toBe(2);
    });

    it('imports an edited file again', function () {
        $dir = researchDir(['2026-10-03-01-roster.xml' => candidateXml('Jane Example')]);
        $this->artisan('elections:import-pending')->assertSuccessful();

        file_put_contents($dir.'/2026-10-03-01-roster.xml', candidateXml('Jane Example', 'mayor'));
        $this->artisan('elections:import-pending')->assertSuccessful();

        expect(Candidate::where('name', 'Jane Example')->value('office'))->toBe('mayor')
            ->and(ImportRecord::count())->toBe(2);
    });

    it('stops at the first failure, rolling that file back and leaving later files pending', function () {
        researchDir([
            '2026-10-03-01-roster.xml' => candidateXml('Jane Example'),
            '2026-10-03-02-broken.xml' => '<not-election/>',
            '2026-10-03-03-later.xml' => candidateXml('Sam Sample'),
        ]);

        $this->artisan('elections:import-pending')
            ->expectsOutputToContain('Stopped: later files were not imported.')
            ->assertFailed();

        expect(ImportRecord::pluck('filename')->all())->toBe(['2026-10-03-01-roster.xml'])
            ->and(Candidate::where('name', 'Sam Sample')->exists())->toBeFalse();
    });

    it('dry-runs every pending file together, then rolls it all back', function () {
        researchDir([
            '2026-10-03-01-roster.xml' => candidateXml('Jane Example'),
            '2026-10-03-02-research.xml' => '<?xml version="1.0" encoding="UTF-8"?><election><plans>'
                .'<plan candidate="Jane Example" key="nope" status="none"/></plans></election>',
        ]);

        $this->artisan('elections:import-pending', ['--dry-run' => true])->assertSuccessful();

        expect(Candidate::count())->toBe(0)->and(ImportRecord::count())->toBe(0);
    });

    it('marks files as imported without importing them', function () {
        researchDir(['0000-snapshot.xml' => candidateXml('Jane Example')]);

        $this->artisan('elections:import-pending', ['--mark-only' => true])->assertSuccessful();

        expect(Candidate::count())->toBe(0)
            ->and(ImportRecord::first()->marked_only)->toBeTrue();
        $this->artisan('elections:import-pending')->expectsOutputToContain('No pending research files.');
    });

    it('does nothing without a research folder', function () {
        config(['elections.import_path' => sys_get_temp_dir().'/no-such-elections-dir']);

        $this->artisan('elections:import-pending')->assertSuccessful();
    });

    it('is scheduled hourly', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'elections:import-pending'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *');
    });
});

describe('<changes>', function () {
    $setup = '<?xml version="1.0" encoding="UTF-8"?><election><candidates><candidate><name>Max Mayor</name><office>mayor</office><status>nominated</status><planks>'
        .'<plank><key>old</key><title>Old</title><topic>housing</topic><summary>S.</summary><tier>top</tier><rank>1</rank><rationale>R.</rationale>'
        .'<entries><entry><summary>Said in January.</summary><source_url>https://example.com/jan</source_url><source_type>news</source_type><published_on>2026-01-10</published_on></entry>'
        .'<entry><summary>Said in September.</summary><source_url>https://example.com/sep</source_url><source_type>news</source_type><published_on>2026-09-10</published_on></entry></entries></plank>'
        .'<plank><key>gone</key><title>Gone</title><topic>housing</topic><summary>S.</summary><tier>also</tier><rank>2</rank><rationale>R.</rationale>'
        .'<entries><entry><summary>Only statement.</summary><source_url>https://example.com/only</source_url><source_type>news</source_type></entry></entries></plank>'
        .'<plank><key>last</key><title>Last</title><topic>housing</topic><summary>S.</summary><tier>also</tier><rank>3</rank><rationale>R.</rationale></plank>'
        .'</planks></candidate></candidates></election>';

    it('refiles pre-campaign statements as record, removes a plank and closes the rank gap', function () use ($setup) {
        $import = app(ImportElectionFromXml::class);
        $import->handleString($setup);

        $result = $import->handleString('<?xml version="1.0" encoding="UTF-8"?><election><changes>'
            .'<detach_entries candidate="Max Mayor" plank="*" before="2026-08-25" kind="prior_record"/>'
            .'<remove_plank candidate="Max Mayor" key="gone"/>'
            .'<remove_plank candidate="Max Mayor" key="never"/>'
            .'<detach_entries candidate="Nobody" plank="*"/>'
            .'</changes></election>');

        $jan = Entry::where('summary', 'Said in January.')->first();
        $only = Entry::where('summary', 'Only statement.')->first();
        expect($jan->kind)->toBe('prior_record')->and($jan->plank_id)->toBeNull()
            ->and(Entry::where('summary', 'Said in September.')->value('plank_id'))->not->toBeNull()
            ->and($only->kind)->toBe('statement')->and($only->plank_id)->toBeNull()
            ->and(Plank::where('key', 'gone')->exists())->toBeFalse()
            ->and(Plank::where('key', 'last')->value('rank'))->toBe(2)
            ->and($result['changes']['updated'])->toBe(2)
            ->and($result['problems'])->toHaveCount(2);
    });

    it('removes one entry by its hash', function () use ($setup) {
        $import = app(ImportElectionFromXml::class);
        $import->handleString($setup);
        $hash = Entry::where('summary', 'Only statement.')->value('match_hash');

        $import->handleString('<?xml version="1.0" encoding="UTF-8"?><election><changes>'
            ."<remove_entry candidate=\"Max Mayor\" hash=\"{$hash}\"/></changes></election>");

        expect(Entry::where('summary', 'Only statement.')->exists())->toBeFalse();
    });
});
