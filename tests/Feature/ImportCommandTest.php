<?php

use Cultpantry\Elections\Models\Candidate;

function electionFile(string $xml): string
{
    $path = tempnam(sys_get_temp_dir(), 'election').'.xml';
    file_put_contents($path, $xml);

    return $path;
}

describe('elections:import', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><election><candidates>'
        .'<candidate><name>Jane Example</name><office>councillor</office><status>nominated</status></candidate>'
        .'</candidates></election>';

    it('imports a file and reports the summary', function () use ($xml) {
        $this->artisan('elections:import', ['files' => [electionFile($xml)]])
            ->expectsOutputToContain('candidates: 1 new')
            ->assertSuccessful();

        expect(Candidate::where('name', 'Jane Example')->exists())->toBeTrue();
    });

    it('rolls everything back on --dry-run', function () use ($xml) {
        $this->artisan('elections:import', ['files' => [electionFile($xml)], '--dry-run' => true])
            ->expectsOutputToContain('candidates: 1 new')
            ->assertSuccessful();

        expect(Candidate::count())->toBe(0);
    });

    it('fails on a missing file or invalid XML', function () {
        $this->artisan('elections:import', ['files' => ['/nope/missing.xml']])->assertFailed();
        $this->artisan('elections:import', ['files' => [electionFile('<not-election/>')]])
            ->expectsOutputToContain('expected an <election> root element')
            ->assertFailed();
    });
});
