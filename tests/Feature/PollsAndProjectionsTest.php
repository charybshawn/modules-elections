<?php

use App\Models\User;
use Cultpantry\Elections\Actions\BuildPollAverage;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\Poll;
use Cultpantry\Elections\Models\RidingProjection;

/*
 * The provincial dashboard's poll average and the model's riding projections.
 */

function pollImport(string $inner, string $election = Election::PROVINCIAL): array
{
    return app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election id="'.$election.'">'.$inner.'</election>');
}

function pollXml(string $pollster, string $to, array $results, string $extra = ''): string
{
    $r = collect($results)->map(fn ($v, $k) => '<result><party>'.$k.'</party><percent>'.$v.'</percent></result>')->implode('');

    return '<poll><pollster>'.$pollster.'</pollster><fielded_to>'.$to.'</fielded_to><sample_size>1000</sample_size><results>'.$r.'</results><source_url>https://poll.example/'.str($pollster)->slug().'</source_url>'.$extra.'</poll>';
}

describe('polls', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
    });

    it('imports a poll once, updates it on a re-import, and skips one with no source or fewer than two parties', function () {
        $first = pollImport('<polls>'.pollXml('Acme', '2026-10-08', ['BC NDP' => 37, 'Conservative Party' => 43]).'</polls>');
        expect($first['polls'])->toBe(['created' => 1, 'updated' => 0, 'unchanged' => 0]);

        $second = pollImport('<polls>'.pollXml('Acme', '2026-10-08', ['BC NDP' => 38, 'Conservative Party' => 43]).'</polls>');
        expect($second['polls'])->toBe(['created' => 0, 'updated' => 1, 'unchanged' => 0])
            ->and(Poll::count())->toBe(1)
            ->and(Poll::first()->results['BC NDP'])->toEqual(38);

        $bad = pollImport('<polls>'.pollXml('One Party', '2026-10-08', ['BC NDP' => 40]).'<poll><pollster>No Source</pollster><fielded_to>2026-10-08</fielded_to><results><result><party>A</party><percent>1</percent></result><result><party>B</party><percent>2</percent></result></results></poll></polls>');
        expect($bad['polls']['created'])->toBe(0)->and($bad['problems'])->toHaveCount(2);
    });

    it('is provincial only', function () {
        $r = pollImport('<polls>'.pollXml('Acme', '2026-10-08', ['A' => 40, 'B' => 41]).'</polls>', Election::MUNICIPAL);
        expect(Poll::count())->toBe(0)->and(collect($r['problems'])->contains(fn ($p) => str_contains($p, 'provincial only')))->toBeTrue();
    });

    it('averages each pollster\'s latest poll inside the window, over the polls that report each party', function () {
        pollImport('<polls>'
            .pollXml('Acme', '2026-10-08', ['BC NDP' => 37, 'Conservative Party' => 43, 'OneBC' => 4])
            .pollXml('Acme', '2026-09-28', ['BC NDP' => 50, 'Conservative Party' => 30])        // superseded by Acme's newer poll
            .pollXml('Beta', '2026-10-01', ['BC NDP' => 43, 'Conservative Party' => 48])         // reports no OneBC
            .pollXml('Gamma', '2026-08-13', ['BC NDP' => 44, 'Conservative Party' => 39])        // outside the 14-day window
            .'</polls>');

        $avg = app(BuildPollAverage::class)->handle();
        expect($avg['polls'])->toHaveCount(4)
            ->and($avg['average']['polls'])->toBe(2)
            ->and($avg['average']['parties']['Conservative Party'])->toBe(['percent' => 45.5, 'polls' => 2])
            ->and($avg['average']['parties']['BC NDP'])->toBe(['percent' => 40.0, 'polls' => 2])
            ->and($avg['average']['parties']['OneBC'])->toBe(['percent' => 4.0, 'polls' => 1])
            ->and(collect($avg['polls'])->where('in_average', true)->pluck('pollster')->sort()->values()->all())->toBe(['Acme', 'Beta'])
            ->and(array_key_first($avg['average']['parties']))->toBe('Conservative Party');
    });

    it('has no average with no polls', function () {
        expect(app(BuildPollAverage::class)->handle())->toBe(['polls' => [], 'average' => null]);
    });
});

describe('riding projections', function () {
    function projectionXml(string $riding, string $rating = 'safe', array $shares = ['Conservative Party' => 55, 'BC NDP' => 27]): string
    {
        $s = collect($shares)->map(fn ($v, $k) => '<share><party>'.$k.'</party><percent>'.$v.'</percent><margin>8</margin></share>')->implode('');

        return '<projection><riding>'.$riding.'</riding><rating>'.$rating.'</rating><as_of>2026-10-11</as_of><source_url>https://model.example/'.str($riding)->slug().'</source_url><shares>'.$s.'</shares></projection>';
    }

    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
    });

    it('imports projections, orders the shares, defaults the leader and matches on riding', function () {
        $r = pollImport('<projections>'.projectionXml('Abbotsford South', 'leaning', ['BC NDP' => 30, 'Conservative Party' => 44]).'</projections>');
        expect($r['projections'])->toBe(['created' => 1, 'updated' => 0, 'unchanged' => 0]);

        $p = RidingProjection::first();
        expect($p->leader)->toBe('Conservative Party')->and($p->shares[0]['party'])->toBe('Conservative Party');

        $again = pollImport('<projections>'.projectionXml('Abbotsford South', 'toss up', ['BC NDP' => 40, 'Conservative Party' => 41]).'</projections>');
        expect($again['projections'])->toBe(['created' => 0, 'updated' => 1, 'unchanged' => 0])->and(RidingProjection::count())->toBe(1);

        $bad = pollImport('<projections><projection><riding>X</riding><as_of>2026-10-11</as_of><shares/></projection></projections>');
        expect($bad['problems'])->not->toBeEmpty();
    });

    it('gives the provincial dashboard the poll panel and this riding\'s projection first, and leaves the municipal one without them', function () {
        pollImport('<polls>'.pollXml('Acme', '2026-10-08', ['BC NDP' => 37, 'Conservative Party' => 43]).'</polls>'
            .'<projections>'.projectionXml('Abbotsford South').projectionXml(Election::provincial()->riding).'</projections>');

        $this->actingAs($this->admin)->get(route('admin.elections.provincial'))
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];
                expect($props['polls']['average']['polls'])->toBe(1)
                    ->and(collect($props['ridingProjections']['rows'])->pluck('riding')->all())->toBe([Election::provincial()->riding, 'Abbotsford South'])
                    ->and($props['ridingProjections']['rows'][0]['is_local'])->toBeTrue()
                    ->and($props['ridingProjections']['as_of'])->toBe('2026-10-11');
            });

        $this->actingAs($this->admin)->get(route('admin.elections.index'))
            ->assertInertia(fn ($page) => expect($page->toArray()['props'])->not->toHaveKeys(['polls', 'ridingProjections']));
    });

    it('exports polls and projections and reads them back unchanged', function () {
        pollImport('<polls>'.pollXml('Acme', '2026-10-08', ['BC NDP' => 37, 'Conservative Party' => 43]).'</polls><projections>'.projectionXml('Abbotsford South').'</projections>');
        $xml = app(ExportElectionToXml::class)->handle(Election::provincial());
        expect($xml)->toContain('<pollster>Acme</pollster>')->and($xml)->toContain('Abbotsford South');

        $again = app(ImportElectionFromXml::class)->handleString($xml);
        expect($again['polls'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1])
            ->and($again['projections'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1]);
    });
});

describe('poll history page', function () {
    it('serves every poll on file with the election-called marker, and a link to it is on the dashboard route', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        pollImport('<polls>'.pollXml('Acme', '2026-10-08', ['BC NDP' => 37, 'Conservative Party' => 43]).pollXml('Beta', '2026-06-01', ['BC NDP' => 41, 'Conservative Party' => 45]).'</polls>');

        $this->actingAs($admin)->get(route('admin.elections.provincial.polls'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];
                expect($props['polls'])->toHaveCount(2)
                    ->and($props['calledOn'])->toBe('2026-09-22')
                    ->and($props['votingDay'])->toBe('2026-10-24')
                    ->and($props['average']['polls'])->toBe(1);
            });
    });
});
