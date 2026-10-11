<?php

use App\Models\User;
use Cultpantry\Elections\Actions\BuildUpdateDigest;
use Cultpantry\Elections\Actions\BuildUpdateFeed;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\ElectionEvent;
use Illuminate\Support\Carbon;

/*
 * The module holds two elections side by side: the Salmon Arm municipal one
 * and the BC provincial one. Nothing from one may show up in the other's
 * pages, feeds or email.
 */

function scopingImport(string $inner, ?string $election = null): array
{
    $root = $election === null ? '<election>' : '<election id="'.$election.'">';

    return app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?>'.$root.$inner.'</election>');
}

function provincialRoster(): string
{
    return '<candidates><candidate><name>Pat Riding</name><office>mla</office><status>declared</status><is_incumbent>true</is_incumbent>'
        .'<entries><entry><kind>statement</kind><topic>housing</topic><summary>Housing first.</summary><source_url>https://party.example/pat</source_url><source_type>other</source_type></entry></entries>'
        .'</candidate></candidates>'
        .'<articles><article><title>Riding race heats up</title><url>https://news.example/riding</url><outlet>Observer</outlet><candidates><candidate>Pat Riding</candidate></candidates></article></articles>'
        .'<events><event><title>Provincial voting day</title><kind>general_voting</kind><starts_at>2028-10-21T08:00</starts_at></event></events>';
}

function municipalRoster(): string
{
    return '<candidates><candidate><name>Jane Example</name><office>councillor</office><status>nominated</status></candidate></candidates>'
        .'<articles><article><title>Council race</title><url>https://news.example/council</url><outlet>Observer</outlet><candidates><candidate>Jane Example</candidate></candidates></article></articles>'
        .'<events><event><title>General voting day</title><kind>general_voting</kind><starts_at>2026-10-17T08:00</starts_at></event></events>';
}

describe('election scoping', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
    });

    it('keeps the two elections seeded, with every old row on the municipal one', function () {
        expect(Election::municipal()->kind)->toBe('municipal')
            ->and(Election::provincial()->riding)->toBe('Salmon Arm-Shuswap')
            ->and(Election::municipal()->offices())->toBe(['mayor' => 'Mayor', 'councillor' => 'Councillor', 'trustee' => 'School trustee'])
            ->and(Election::provincial()->offices())->toBe(['mla' => 'MLA']);

        // A row made the way the old code did lands on the municipal election.
        expect(Candidate::create(['name' => 'Old Style', 'office' => 'mayor'])->election_id)->toBe(Election::municipal()->id)
            ->and(ElectionEvent::create(['title' => 'Old', 'starts_at' => '2026-10-01 10:00:00'])->election_id)->toBe(Election::municipal()->id);
    });

    it('imports a file with no election id as municipal and one with an id as that election', function () {
        $legacy = scopingImport(municipalRoster());
        expect($legacy['problems'])->toBe([]);

        $provincial = scopingImport(provincialRoster(), Election::PROVINCIAL);
        expect($provincial['problems'])->toBe([]);

        expect(Candidate::where('name', 'Jane Example')->value('election_id'))->toBe(Election::municipal()->id)
            ->and(Candidate::where('name', 'Pat Riding')->value('election_id'))->toBe(Election::provincial()->id)
            ->and(Article::where('title', 'Riding race heats up')->value('election_id'))->toBe(Election::provincial()->id)
            ->and(ElectionEvent::where('title', 'Provincial voting day')->value('election_id'))->toBe(Election::provincial()->id);
    });

    it('rejects an unknown election id and an office from the other election', function () {
        expect(fn () => scopingImport(municipalRoster(), 'nowhere-2030'))->toThrow(RuntimeException::class, 'Unknown election id');

        $result = scopingImport('<candidates><candidate><name>Mixed Up</name><office>mayor</office></candidate></candidates>', Election::PROVINCIAL);
        expect(Candidate::where('name', 'Mixed Up')->exists())->toBeFalse()
            ->and(collect($result['problems'])->join(' '))->toContain('Skipped new candidate Mixed Up', 'mla');

        $result = scopingImport('<candidates><candidate><name>Wrong Seat</name><office>mla</office></candidate></candidates>');
        expect(Candidate::where('name', 'Wrong Seat')->exists())->toBeFalse();
    });

    it('lets the same name stand in both elections without one touching the other', function () {
        scopingImport('<candidates><candidate><name>Sam Both</name><office>councillor</office><occupation>Teacher</occupation></candidate></candidates>');
        scopingImport('<candidates><candidate><name>Sam Both</name><office>mla</office><occupation>Pilot</occupation></candidate></candidates>', Election::PROVINCIAL);
        scopingImport('<candidates><candidate><name>Sam Both</name><occupation>Carpenter</occupation></candidate></candidates>');

        $both = Candidate::where('name', 'Sam Both')->orderBy('election_id')->get();
        expect($both)->toHaveCount(2)
            ->and($both[0]->slug)->not->toBe($both[1]->slug)
            ->and($both[0]->occupation)->toBe('Carpenter')
            ->and($both[0]->office)->toBe('councillor')
            ->and($both[1]->occupation)->toBe('Pilot');
    });

    it('lets the same story and event exist in both elections', function () {
        $article = '<articles><article><title>Everyone votes</title><url>https://news.example/both</url></article></articles>';
        $event = '<events><event><title>Voting</title><kind>general_voting</kind><starts_at>2026-10-17T08:00</starts_at></event></events>';
        scopingImport($article.$event);
        scopingImport($article.$event, Election::PROVINCIAL);
        scopingImport($article.$event);

        expect(Article::where('title', 'Everyone votes')->count())->toBe(2)
            ->and(ElectionEvent::where('title', 'Voting')->count())->toBe(2);
    });

    it('keeps provincial rows out of every municipal page, feed and email', function () {
        $this->travelTo(Carbon::parse('2026-10-11 09:00:00'));
        scopingImport(municipalRoster());
        $since = now(); // after the municipal import, so only the provincial one is in the window
        $this->travelTo(Carbon::parse('2026-10-11 12:00:00'));
        scopingImport(provincialRoster(), Election::PROVINCIAL);

        $admin = $this->actingAs($this->admin);
        $pat = Candidate::where('name', 'Pat Riding')->first();

        // Dashboard: candidates, stats, events, coverage and latest updates.
        $admin->get(route('admin.elections.index'))->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            expect(collect($props['candidates'])->pluck('name')->all())->toBe(['Jane Example'])
                ->and($props['stats'])->toMatchArray(['candidates' => 1, 'articles' => 1])
                ->and(collect($props['recentArticles'])->pluck('title')->all())->toBe(['Council race'])
                ->and(collect($props['upcomingEvents'])->merge($props['pastEvents'])->pluck('title')->all())->toBe(['General voting day'])
                ->and(collect($props['latestUpdates'])->pluck('name')->filter()->unique()->all())->toBe([])
                ->and(collect($props['latestUpdates'])->where('type', 'article')->pluck('title')->all())->toBe(['Council race']);
        });

        // The provincial dashboard shows only the riding.
        $admin->get(route('admin.elections.provincial'))->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            expect(collect($props['candidates'])->pluck('name')->all())->toBe(['Pat Riding'])
                ->and(collect($props['recentArticles'])->pluck('title')->all())->toBe(['Riding race heats up'])
                ->and(collect($props['latestUpdates'])->pluck('name')->filter()->unique()->values()->all())->toBe(['Pat Riding'])
                ->and($props['heat'])->toBe(['headings' => [], 'heatingUp' => []]);
        });

        // The update feed, per election.
        $municipalFeed = $admin->getJson(route('admin.elections.updates'))->json();
        expect(array_keys($municipalFeed['candidates']))->toBe(['jane-example'])
            ->and($municipalFeed['sections']['events'])->toHaveCount(1);
        $provincialFeed = $admin->getJson(route('admin.elections.updates', ['election' => Election::PROVINCIAL]))->json();
        expect(array_keys($provincialFeed['candidates']))->toBe([$pat->slug])
            ->and($provincialFeed['sections']['pulse'])->toBe([])
            ->and($provincialFeed['sections']['subjects'])->toBe([])
            ->and($provincialFeed['sections']['plans'])->toBe([]);
        $admin->getJson(route('admin.elections.updates', ['election' => 'nowhere']))->assertNotFound();

        // Search, compare and subjects stay municipal; provincial search is its own.
        $search = $admin->getJson(route('admin.elections.search-index'))->json();
        expect(collect($search['candidates'])->pluck('name')->all())->toBe(['Jane Example']);
        $search = $admin->getJson(route('admin.elections.search-index', ['election' => Election::PROVINCIAL]))->json();
        expect(collect($search['candidates'])->pluck('name')->all())->toBe(['Pat Riding'])
            ->and($search['subjects'])->toBe([]);
        $admin->get(route('admin.elections.compare'))->assertInertia(fn ($page) => $page
            ->where('everyone', fn ($e) => collect($e)->pluck('name')->all() === ['Jane Example']));

        // The email digest covers the municipal election only.
        $digest = app(BuildUpdateDigest::class)->handle($since, now());
        expect($digest['articles'])->toBe(0)->and($digest['events'])->toBe(0)->and($digest['total'])->toBe(0);
    });

    it('exports each election on its own', function () {
        scopingImport(municipalRoster());
        scopingImport(provincialRoster(), Election::PROVINCIAL);

        $municipal = app(ExportElectionToXml::class)->handle();
        $provincial = app(ExportElectionToXml::class)->handle(Election::provincial());

        expect($municipal)->toContain('<election id="salmon-arm-2026">', 'Jane Example')->not->toContain('Pat Riding')
            ->and($provincial)->toContain('<election id="bc-provincial-salmon-arm-shuswap">', 'Pat Riding', 'Provincial voting day')->not->toContain('Jane Example');

        $admin = $this->actingAs($this->admin);
        $admin->get(route('admin.elections.export'))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="salmon-arm-election-'.now()->toDateString().'.xml"');
        $admin->get(route('admin.elections.export', ['election' => Election::PROVINCIAL]))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="bc-provincial-election-'.now()->toDateString().'.xml"');

        // And the export reads back into the same election.
        Candidate::query()->delete();
        $result = app(ImportElectionFromXml::class)->handleString($provincial);
        expect($result['problems'])->toBe([])
            ->and(Candidate::where('name', 'Pat Riding')->value('election_id'))->toBe(Election::provincial()->id);
    });

    it('carries a candidate\'s party through import, export and the dashboard', function () {
        scopingImport('<candidates><candidate><name>Pat Riding</name><office>mla</office><party>BC NDP</party></candidate></candidates>', Election::PROVINCIAL);
        // A later file that leaves the party out doesn't wipe it.
        scopingImport('<candidates><candidate><name>Pat Riding</name><occupation>Teacher</occupation></candidate></candidates>', Election::PROVINCIAL);

        expect(Candidate::where('name', 'Pat Riding')->value('party'))->toBe('BC NDP')
            ->and(app(ExportElectionToXml::class)->handle(Election::provincial()))->toContain('<party>BC NDP</party>');

        $this->actingAs($this->admin)->get(route('admin.elections.provincial'))
            ->assertInertia(fn ($page) => $page->where('candidates.0.party', 'BC NDP'));
    });

    it('says plainly that pulse and scorecards are not supported for the provincial election yet', function () {
        $result = scopingImport('<pulse><taken_on>2026-10-11</taken_on></pulse>', Election::PROVINCIAL);

        expect(collect($result['problems'])->join(' '))->toContain('not supported for the provincial election yet');
    });
});
