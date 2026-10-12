<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\Tag;
use Cultpantry\Elections\Models\Vote;

/*
 * The voting record: recorded votes by member, grouped by year on the record
 * tab beside the narrative items, taggable by subject, imported and exported
 * as <votes> under a candidate.
 */

function voteXml(string $extra = ''): string
{
    return '<vocabulary><tag><slug>energy</slug><topic>infrastructure</topic><name>Energy</name></tag></vocabulary>'
        .'<candidates><candidate><name>Pat Riding</name><office>mla</office><is_incumbent>true</is_incumbent>'
        .'<entries><entry><kind>prior_record</kind><topic>other</topic><summary>Led a delegation in 2025.</summary><published_on>2025-06-01</published_on>'
        .'<source_url>https://news.example/delegation</source_url><source_type>news</source_type></entry></entries>'
        .'<votes>'
        .'<vote><body>legislature</body><voted_on>2025-04-15</voted_on><title>Bill 7: Tariff Response Act, third reading</title><reference>Bill 7</reference>'
        .'<description>Motion agreed to on the following division.</description><vote>nay</vote><outcome>passed</outcome><tally_for>46</tally_for><tally_against>43</tally_against>'
        .'<source_url>https://leg.example/v250415.pdf</source_url><source_name>Votes and Proceedings</source_name><tags><tag>energy</tag></tags></vote>'
        .'<vote><body>legislature</body><voted_on>2026-02-12</voted_on><title>Bill 9: Privacy Act, second reading</title><reference>Bill 9</reference>'
        .'<vote>yea</vote><outcome>defeated</outcome><source_url>https://leg.example/v260212.pdf</source_url></vote>'
        .$extra.'</votes></candidate></candidates>';
}

function voteImport(string $inner): array
{
    return app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election id="'.Election::PROVINCIAL.'">'.$inner.'</election>');
}

describe('voting record', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
    });

    it('imports votes under a candidate, tagged, and updates rather than duplicates on re-import', function () {
        $first = voteImport(voteXml());
        expect($first['problems'])->toBe([])->and($first['votes']['created'])->toBe(2);

        $second = voteImport(voteXml());
        expect($second['votes'])->toMatchArray(['created' => 0, 'updated' => 0, 'unchanged' => 2]);

        $vote = Vote::where('reference', 'Bill 7')->first();
        expect($vote->vote)->toBe('nay')->and($vote->tally_for)->toBe(46)->and($vote->tags->pluck('slug')->all())->toBe(['energy'])
            ->and(Tag::where('slug', 'energy')->first()->votes()->count())->toBe(1);
    });

    it('reports a vote that is missing its record, date, kind or body, and files nothing for it', function () {
        $bad = '<vote><body>legislature</body><voted_on>2026-01-01</voted_on><title>No source</title><vote>yea</vote></vote>'
            .'<vote><body>legislature</body><voted_on>2026-01-02</voted_on><title>Odd vote</title><vote>maybe</vote><source_url>https://leg.example/x</source_url></vote>'
            .'<vote><body>parliament</body><voted_on>2026-01-03</voted_on><title>Odd body</title><vote>yea</vote><source_url>https://leg.example/y</source_url></vote>';
        $result = voteImport(voteXml($bad));

        expect($result['votes']['created'])->toBe(2)
            ->and(collect($result['problems'])->join(' '))->toContain('no http(s) <source_url>', 'must be one of: yea, nay, abstained', '<body> must be one of');
    });

    it('groups the record tab by year, newest first, with votes and narrative items together', function () {
        voteImport(voteXml());
        $candidate = Candidate::where('name', 'Pat Riding')->first();

        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => $candidate->slug, 'tab' => 'record']))
            ->assertInertia(function ($page) {
                $record = collect($page->toArray()['props']['portfolio']['sections'])->firstWhere('key', 'record');
                expect($record['title'])->toBe('Voting record')->and($record['vote_count'])->toBe(2)
                    ->and(collect($record['years'])->pluck('year')->all())->toBe([2026, 2025])
                    ->and(collect($record['years'][1]['items'])->pluck('type')->all())->toBe(['entry', 'vote'])
                    ->and($record['years'][0]['items'][0])->toMatchArray(['type' => 'vote', 'vote' => 'yea', 'reference' => 'Bill 9']);
            });
    });

    it('leaves a municipal candidate\'s record tab as it was: Prior record, grouped by topic, no years', function () {
        app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election><candidates><candidate><name>Jane Councillor</name><office>councillor</office><status>nominated</status><is_incumbent>true</is_incumbent>'
            .'<entries><entry><kind>prior_record</kind><topic>housing</topic><summary>Voted for the rezoning on Aug. 24, 2026.</summary><source_url>https://news.example/rezoning</source_url><source_type>news</source_type></entry></entries></candidate></candidates></election>');

        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => 'jane-councillor', 'tab' => 'record']))
            ->assertInertia(function ($page) {
                $record = collect($page->toArray()['props']['portfolio']['sections'])->firstWhere('key', 'record');
                expect($record['title'])->toBe('Prior record')
                    ->and($record)->not->toHaveKey('years')
                    ->and($record['groups'][0]['topic'])->toBe('housing')
                    ->and($record['groups'][0]['entries'])->toHaveCount(1);
            });
    });

    it('renders each election with its own page files, so a change to one cannot change the other', function () {
        voteImport(voteXml());
        app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election><candidates><candidate><name>Jane Councillor</name><office>councillor</office><status>nominated</status></candidate></candidates></election>');

        $admin = $this->actingAs($this->admin);
        $admin->get(route('admin.elections.provincial'))->assertInertia(fn ($page) => $page->component('Vendor/elections/Provincial/Index', false));
        $admin->get(route('admin.elections.index'))->assertInertia(fn ($page) => $page->component('Vendor/elections/Index', false));
        $admin->get(route('admin.elections.candidates.show', 'pat-riding'))->assertInertia(fn ($page) => $page->component('Vendor/elections/Provincial/Candidates/Show', false));
        $admin->get(route('admin.elections.candidates.show', 'jane-councillor'))->assertInertia(fn ($page) => $page->component('Vendor/elections/Candidates/Show', false));
    });

    it('shows a bill as one entry: its stage votes first, amendments and committee votes folded under it', function () {
        $billed = fn (string $on, string $title, string $stage, string $how, string $ref = 'Bill 7') => '<vote><body>legislature</body><voted_on>'.$on.'</voted_on><title>'.$title.'</title>'
            .'<reference>'.$ref.'</reference><bill_key>43rd1st:Bill 7</bill_key><bill_title>Bill 7: Tariff Response Act</bill_title><stage>'.$stage.'</stage>'
            .'<vote>'.$how.'</vote><outcome>'.($stage === 'amendment' ? 'defeated' : 'passed').'</outcome><source_url>https://leg.example/'.$on.'</source_url></vote>';

        $result = voteImport('<candidates><candidate><name>Pat Riding</name><office>mla</office><votes>'
            .$billed('2025-05-28', 'Bill 7 — third reading', 'third_reading', 'nay')
            .$billed('2025-04-03', 'Bill 7 — second reading', 'second_reading', 'nay')
            .$billed('2025-04-16', 'Bill 7 — amendment to clause 3', 'amendment', 'yea')
            .$billed('2025-04-17', 'Bill 7 — clause 7', 'clause', 'nay')
            .'<vote><body>legislature</body><voted_on>2025-06-02</voted_on><title>Motion that the House adjourn</title><vote>yea</vote><source_url>https://leg.example/m</source_url></vote>'
            .'</votes></candidate></candidates>');
        expect($result['problems'])->toBe([]);

        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => 'pat-riding', 'tab' => 'record']))
            ->assertInertia(function ($page) {
                $years = collect($page->toArray()['props']['portfolio']['sections'])->firstWhere('key', 'record')['years'];
                $items = collect($years)->flatMap(fn ($y) => $y['items']);
                $bill = $items->firstWhere('type', 'bill');

                expect($items->pluck('type')->sort()->values()->all())->toBe(['bill', 'vote'])
                    ->and($bill['bill_title'])->toBe('Bill 7: Tariff Response Act')
                    ->and(collect($bill['stage_votes'])->pluck('stage')->all())->toBe(['second_reading', 'third_reading'])
                    ->and(collect($bill['other_votes'])->pluck('stage')->all())->toBe(['amendment', 'clause'])
                    ->and($bill['vote_count'])->toBe(4)
                    ->and(collect($years)->first()['votes'])->toBe(5);
            });
    });

    it('attaches the two synopses to a bill, with what they rest on, and skips notes it cannot place', function () {
        $bill = fn (string $stage, string $how) => '<vote><body>legislature</body><voted_on>2025-05-28</voted_on><title>Bill 7 — '.$stage.'</title>'
            .'<reference>Bill 7</reference><bill_key>43rd1st:Bill 7</bill_key><bill_title>Bill 7: Tariff Response Act</bill_title><stage>'.$stage.'</stage>'
            .'<vote>'.$how.'</vote><outcome>passed</outcome><source_url>https://leg.example/v.pdf</source_url></vote>';

        $result = voteImport('<candidates><candidate><name>Pat Riding</name><office>mla</office><votes>'.$bill('third_reading', 'nay').'</votes>'
            .'<bill_notes>'
            .'<bill_note><bill_key>43rd1st:Bill 7</bill_key><purpose>Lets government respond to tariffs.</purpose>'
            .'<vote_analysis>Voted nay; said the powers were too broad. Passed 48–44.</vote_analysis><basis>spoke</basis>'
            .'<sources><source><label>Bill text</label><url>https://leg.example/bill7.htm</url></source><source><label>bad</label><url>javascript:alert(1)</url></source></sources></bill_note>'
            .'<bill_note><bill_key>43rd1st:Bill 99</bill_key><purpose>No votes on file for this bill.</purpose><vote_analysis>x</vote_analysis></bill_note>'
            .'<bill_note><bill_key>43rd1st:Bill 7</bill_key></bill_note>'
            .'</bill_notes></candidate></candidates>');

        expect($result['bill_notes']['created'])->toBe(1)
            ->and(collect($result['problems'])->contains(fn ($p) => str_contains($p, 'Bill 99')))->toBeTrue()
            ->and(collect($result['problems'])->contains(fn ($p) => str_contains($p, 'valid <url>')))->toBeTrue();

        // Importing again updates the note rather than adding a second.
        $again = voteImport('<candidates><candidate><name>Pat Riding</name><office>mla</office><bill_notes><bill_note><bill_key>43rd1st:Bill 7</bill_key>'
            .'<purpose>Lets government respond to US tariffs.</purpose><vote_analysis>Voted nay.</vote_analysis><basis>record</basis></bill_note></bill_notes></candidate></candidates>');
        expect($again['bill_notes']['updated'])->toBe(1)->and(\Cultpantry\Elections\Models\BillNote::count())->toBe(1);

        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => 'pat-riding', 'tab' => 'record']))
            ->assertInertia(function ($page) {
                $years = collect($page->toArray()['props']['portfolio']['sections'])->firstWhere('key', 'record')['years'];
                $bill = collect($years)->flatMap(fn ($y) => $y['items'])->firstWhere('type', 'bill');

                expect($bill['note']['purpose'])->toBe('Lets government respond to US tariffs.')
                    ->and($bill['note']['vote_analysis'])->toBe('Voted nay.')
                    ->and($bill['note']['basis'])->toBe('record');
            });
    });

    it('lists what a member introduced themselves, and skips items it cannot place', function () {
        $item = fn (string $kind, string $status, string $url = 'https://leg.example/m246.htm') => '<item><kind>'.$kind.'</kind><reference>Bill M246</reference>'
            .'<session>43rd Parliament, 2nd session</session><title>Small Farms Act</title><introduced_on>2026-05-21</introduced_on>'
            .'<status>'.$status.'</status><status_note>First reading only</status_note><source_url>'.$url.'</source_url></item>';

        $result = voteImport('<candidates><candidate><name>Pat Riding</name><office>mla</office><sponsored>'
            .$item('bill', 'introduced')
            .$item('petition', 'introduced')
            .$item('bill', 'sideways')
            .$item('bill', 'introduced', 'not a url')
            .'</sponsored></candidate></candidates>');

        expect($result['sponsored']['created'])->toBe(1)
            ->and($result['problems'])->toHaveCount(3);

        $again = voteImport('<candidates><candidate><name>Pat Riding</name><office>mla</office><sponsored>'.$item('bill', 'second_reading').'</sponsored></candidate></candidates>');
        expect($again['sponsored']['updated'])->toBe(1)->and(\Cultpantry\Elections\Models\Sponsorship::count())->toBe(1);

        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => 'pat-riding', 'tab' => 'record']))
            ->assertInertia(function ($page) {
                $record = collect($page->toArray()['props']['portfolio']['sections'])->firstWhere('key', 'record');

                expect($record['sponsored'])->toHaveCount(1)
                    ->and($record['sponsored'][0]['title'])->toBe('Small Farms Act')
                    ->and($record['sponsored'][0]['status'])->toBe('second_reading');
            });
    });

    it('exports votes and reads them back, and lets an admin delete one with an audit entry', function () {
        voteImport(voteXml());

        $xml = app(ExportElectionToXml::class)->handle(Election::provincial());
        expect($xml)->toContain('<vote>nay</vote>', '<tally_for>46</tally_for>', '<reference>Bill 7</reference>');

        $candidate = Candidate::where('name', 'Pat Riding')->first();
        $vote = Vote::where('reference', 'Bill 7')->first();
        $this->actingAs($this->admin)->delete(route('admin.elections.votes.destroy', [$candidate, $vote]))->assertRedirect();
        expect(Vote::count())->toBe(1);
    });

    it('lets the tag page and the update feed see votes without leaking into the municipal election', function () {
        voteImport(voteXml());
        $feed = $this->actingAs($this->admin)->getJson(route('admin.elections.updates', ['election' => Election::PROVINCIAL]))->json();
        $slug = Candidate::where('name', 'Pat Riding')->value('slug');
        expect(array_keys($feed['candidates'][$slug]))->toContain('record');

        $municipal = $this->actingAs($this->admin)->getJson(route('admin.elections.updates'))->json();
        expect($municipal['candidates'])->toBe([]);
    });
});

it('keeps a subject tag used only by provincial votes out of the municipal subject views', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    voteImport(voteXml());

    $this->actingAs($admin)->get(route('admin.elections.tags.index'))->assertInertia(function ($page) {
        $slugs = collect($page->toArray()['props']['headings'])->pluck('tags')->flatten(1)->pluck('slug')->all();
        expect($slugs)->not->toContain('energy');
    });
    $search = $this->actingAs($admin)->getJson(route('admin.elections.search-index'))->json();
    expect(collect($search['subjects'])->pluck('slug')->all())->not->toContain('energy');
});
