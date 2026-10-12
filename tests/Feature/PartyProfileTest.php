<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\Party;

/*
 * The party profile under a provincial candidate's occupation: who leads the
 * party, how long it has existed, its record in government, what it stands for.
 */

function partyImport(string $inner, string $election = Election::PROVINCIAL): array
{
    return app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election id="'.$election.'">'.$inner.'</election>');
}

function partyXml(string $name = 'BC NDP', string $leader = 'David Eby'): string
{
    return '<parties><party><name>'.$name.'</name><full_name>The Party</full_name><leader>'.$leader.'</leader><leader_note>Since 2022.</leader_note>'
        .'<founded>1933</founded><times_in_government>3</times_in_government><government_note>1972, 1991, 2017.</government_note>'
        .'<principles>Social democracy.</principles><platform><point>Freeze ICBC rates.</point><point>Cap grocery margins.</point></platform>'
        .'<as_of>2026-10-12</as_of><sources><source><label>Party site</label><url>https://party.example/plan</url></source><source><label>bad</label><url>javascript:x</url></source></sources></party></parties>';
}

describe('party profiles', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
    });

    it('imports a party once, updates it on a re-import, and drops a source with no valid address', function () {
        $first = partyImport(partyXml());
        expect($first['parties']['created'])->toBe(1)
            ->and(collect($first['problems'])->contains(fn ($p) => str_contains($p, 'valid <url>')))->toBeTrue();

        $second = partyImport(partyXml('BC NDP', 'Someone Else'));
        expect($second['parties']['updated'])->toBe(1)->and(Party::count())->toBe(1);

        $party = Party::first();
        expect($party->leader)->toBe('Someone Else')
            ->and($party->times_in_government)->toBe(3)
            ->and($party->platform)->toBe(['Freeze ICBC rates.', 'Cap grocery margins.'])
            ->and($party->sources)->toHaveCount(1)
            ->and($party->as_of->toDateString())->toBe('2026-10-12');
    });

    it('needs a name and a checked-on date, and is provincial only', function () {
        $r = partyImport('<parties><party><name>No Date</name></party></parties>');
        expect($r['parties']['created'])->toBe(0)->and($r['problems'])->not->toBeEmpty();

        $municipal = partyImport(partyXml(), Election::MUNICIPAL);
        expect(Party::count())->toBe(0)
            ->and(collect($municipal['problems'])->contains(fn ($p) => str_contains($p, 'provincial only')))->toBeTrue();
    });

    it('shows the profile for a provincial candidate whose party is on file, and nothing for one without', function () {
        partyImport(partyXml('BC NDP')
            .'<candidates><candidate><name>Pat Riding</name><office>mla</office><party>BC NDP</party></candidate>'
            .'<candidate><name>Sam Other</name><office>mla</office><party>Unlisted Party</party></candidate></candidates>');

        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => 'pat-riding']))
            ->assertInertia(function ($page) {
                $p = $page->toArray()['props']['portfolio']['party_profile'];
                expect($p['leader'])->toBe('David Eby')
                    ->and($p['times_in_government'])->toBe(3)
                    ->and($p['platform'])->toHaveCount(2)
                    ->and($p['as_of'])->toBe('2026-10-12');
            });

        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => 'sam-other']))
            ->assertInertia(fn ($page) => expect($page->toArray()['props']['portfolio']['party_profile'])->toBeNull());
    });

    it('exports the parties for the provincial election only, and reads them back unchanged', function () {
        partyImport(partyXml());
        $xml = app(ExportElectionToXml::class)->handle(Election::provincial());
        expect($xml)->toContain('<party>')->and($xml)->toContain('Freeze ICBC rates.');
        expect(app(ExportElectionToXml::class)->handle(Election::municipal()))->not->toContain('<parties>');

        $again = app(ImportElectionFromXml::class)->handleString($xml);
        expect($again['parties'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1]);
    });
});

function partyChecksXml(string $rating = 'mostly_holds_up'): string
{
    return '<parties><party><name>BC NDP</name><claims>'
        .'<claim><key>jobs</key><kind>claim</kind><claim>300,000 jobs since 2017.</claim><source_url>https://party.example/plan</source_url><topic>Economy</topic>'
        .'<rating>'.$rating.'</rating><summary>Statistics Canada agrees.</summary><checked_on>2026-10-12</checked_on>'
        .'<evidence><item><kind>government_record</kind><text>Employment rose.</text><label>StatCan</label><url>https://statcan.example/t</url></item>'
        .'<item><kind>hearsay</kind><text>Dropped.</text></item></evidence></claim>'
        .'<claim><key>icbc</key><kind>pledge</kind><claim>Freeze rates.</claim><source_url>https://party.example/plan</source_url>'
        .'<rating>untestable</rating><summary>A pledge.</summary><checked_on>2026-10-12</checked_on></claim>'
        .'</claims><news><article><title>Party promises a freeze</title><url>https://news.example/a</url><outlet>Example</outlet><published_on>2026-10-08</published_on><type>opinion</type><summary>Story.</summary></article></news>'
        .'</party></parties>';
}

describe('party page, fact-checks and news', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
        partyImport(partyXml('BC NDP')
            .'<candidates><candidate><name>Pat Riding</name><office>mla</office><party>BC NDP</party></candidate></candidates>');
    });

    it('adds claims and news to an existing party without touching its profile, and matches them on re-import', function () {
        $r = partyImport(partyChecksXml());
        expect($r['party_claims'])->toBe(['created' => 2, 'updated' => 0, 'unchanged' => 0])
            ->and($r['party_news'])->toBe(['created' => 1, 'updated' => 0, 'unchanged' => 0])
            ->and(collect($r['problems'])->contains(fn ($p) => str_contains($p, 'evidence item')))->toBeTrue();

        $party = Party::first();
        expect($party->leader)->toBe('David Eby')
            ->and($party->claims)->toHaveCount(2)
            ->and($party->claims->firstWhere('key', 'jobs')->evidence)->toHaveCount(1);

        $again = partyImport(partyChecksXml('mixed'));
        expect($again['party_claims'])->toBe(['created' => 0, 'updated' => 1, 'unchanged' => 1])
            ->and($again['party_news'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1])
            ->and($party->claims()->where('key', 'jobs')->first()->rating)->toBe('mixed');
    });

    it('skips a claim with an unknown rating or no source address', function () {
        $r = partyImport('<parties><party><name>BC NDP</name><claims>'
            .'<claim><key>a</key><claim>X</claim><source_url>https://p.example</source_url><rating>true</rating><summary>s</summary><checked_on>2026-10-12</checked_on></claim>'
            .'<claim><key>b</key><claim>Y</claim><rating>holds_up</rating><summary>s</summary><checked_on>2026-10-12</checked_on></claim>'
            .'</claims></party></parties>');
        expect($r['party_claims']['created'])->toBe(0)->and($r['problems'])->toHaveCount(2);
    });

    it('serves the party page with its candidates, claims, rating counts and news, and links to it from the main page', function () {
        partyImport(partyChecksXml());

        $this->actingAs($this->admin)->get(route('admin.elections.provincial.parties.show', ['party' => 'bc-ndp']))
            ->assertOk()
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];
                expect($props['party']['name'])->toBe('BC NDP')
                    ->and(collect($props['candidates'])->pluck('name')->all())->toBe(['Pat Riding'])
                    ->and($props['claims'])->toHaveCount(2)
                    ->and($props['ratingCounts'])->toBe(['mostly_holds_up' => 1, 'untestable' => 1])
                    ->and($props['articles'][0]['type'])->toBe('opinion');
            });

        $this->actingAs($this->admin)->get(route('admin.elections.provincial'))
            ->assertInertia(fn ($page) => expect($page->toArray()['props']['partyPages'])->toBe(['BC NDP' => 'bc-ndp']));

        $this->actingAs($this->admin)->get(route('admin.elections.index'))
            ->assertInertia(fn ($page) => expect($page->toArray()['props'])->not->toHaveKey('partyPages'));
    });

    it('exports claims and news and reads them back unchanged', function () {
        partyImport(partyChecksXml());
        $xml = app(ExportElectionToXml::class)->handle(Election::provincial());
        expect($xml)->toContain('<key>jobs</key>')->and($xml)->toContain('Party promises a freeze');

        $again = app(ImportElectionFromXml::class)->handleString($xml);
        expect($again['party_claims'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 2])
            ->and($again['party_news'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1]);
    });
});

describe('party slate (candidates across the province)', function () {
    function slateXml(array $rows): string
    {
        $c = collect($rows)->map(fn ($r) => '<candidate><riding>'.$r[0].'</riding><name>'.$r[1].'</name>'.(isset($r[2]) ? '<incumbent_riding>'.$r[2].'</incumbent_riding>' : '').'</candidate>')->implode('');

        return '<parties><party><name>BC NDP</name><slate><source_url>https://elections.example/list</source_url><as_of>2026-10-12</as_of>'.$c.'</slate></party></parties>';
    }

    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
        partyImport(partyXml('BC NDP'));
    });

    it('imports the whole list, matches on re-import, and drops a candidate the new list no longer names', function () {
        $first = partyImport(slateXml([['Abbotsford South', 'Leah Ballantyne'], ['Salmon Arm-Shuswap', 'Gloria Morgan']]));
        expect($first['party_slate'])->toBe(['created' => 2, 'updated' => 0, 'unchanged' => 0]);

        $second = partyImport(slateXml([['Salmon Arm-Shuswap', 'Gloria Morgan']]));
        expect($second['party_slate'])->toBe(['created' => 0, 'updated' => 1, 'unchanged' => 0])
            ->and(Party::first()->slate)->toHaveCount(1)
            ->and(Party::first()->slate_as_of->toDateString())->toBe('2026-10-12');
    });

    it('skips a slate with no valid source and reports it', function () {
        $r = partyImport('<parties><party><name>BC NDP</name><slate><as_of>2026-10-12</as_of><candidate><riding>X</riding><name>Y</name></candidate></slate></party></parties>');
        expect($r['party_slate']['created'])->toBe(0)->and($r['problems'])->not->toBeEmpty();
    });

    it('serves the slate on the party page, marking this riding\'s candidate, and round-trips through export', function () {
        partyImport(partyXml('BC NDP').'<candidates><candidate><name>Gloria Morgan</name><office>mla</office><party>BC NDP</party></candidate></candidates>');
        partyImport(slateXml([['Abbotsford South', 'Leah Ballantyne', 'Abbotsford West'], ['Salmon Arm-Shuswap', 'Gloria Morgan']]));

        $this->actingAs($this->admin)->get(route('admin.elections.provincial.parties.show', ['party' => 'bc-ndp']))
            ->assertInertia(function ($page) {
                $slate = $page->toArray()['props']['slate'];
                expect($slate['candidates'])->toHaveCount(2)
                    ->and(collect($slate['candidates'])->pluck('incumbent_riding', 'name')->all())->toBe(['Leah Ballantyne' => 'Abbotsford West', 'Gloria Morgan' => null])
                    ->and($slate['local'])->toBe(['Gloria Morgan' => 'gloria-morgan'])
                    ->and($slate['source_url'])->toBe('https://elections.example/list');
            });

        $xml = app(ExportElectionToXml::class)->handle(Election::provincial());
        expect($xml)->toContain('<slate>')->and($xml)->toContain('Leah Ballantyne');
        $again = app(ImportElectionFromXml::class)->handleString($xml);
        expect($again['party_slate'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 2]);
    });
});
