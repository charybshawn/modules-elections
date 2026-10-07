<?php

use App\Models\User;
use Cultpantry\Elections\Actions\BuildCandidatePortfolio;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Support\CityPlans;
use Illuminate\Support\Carbon;

/*
 * The update feed behind every "new since your last visit" marker: each kind
 * of new data must light up exactly the tab or section it appears on, and
 * data that didn't change must light up nothing.
 *
 * Every case loads a base field at 09:00, takes the viewer's "last look" at
 * 10:00, imports one change at 12:00 and asks which scopes have items newer
 * than the last look.
 */

function feedImport(string $inner): void
{
    $result = app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election>'.$inner.'</election>');
    expect($result['problems'])->toBe([]);
}

function feedEntry(string $kind, string $topic, string $summary, string $url): string
{
    return "<entry><kind>{$kind}</kind><topic>{$topic}</topic><summary>{$summary}</summary><source_url>{$url}</source_url><source_type>candidate_site</source_type></entry>";
}

/** Jane, with a fact in every tab, a plank, a news story, a scorecard, an event, a pulse snapshot and a tag. */
function feedBase(string $bio = 'Jane is a teacher.', string $notes = 'Checked twice.', string $stance = 'supportive'): string
{
    $facts = [
        feedEntry('background', 'career', 'Teaches.', 'https://example.com/career'),
        feedEntry('background', 'community', 'Coaches soccer.', 'https://example.com/community'),
        feedEntry('statement', 'housing', 'Wants more suites.', 'https://example.com/statement'),
        feedEntry('prior_record', 'taxes_budget', 'Voted for the budget.', 'https://example.com/record'),
        feedEntry('endorsement', 'other', 'Endorsed by the chamber.', 'https://example.com/endorse'),
        feedEntry('finance', 'other', 'Raised $500.', 'https://example.com/finance'),
    ];

    return '<vocabulary><tag><slug>housing</slug><topic>housing</topic><name>Housing</name></tag></vocabulary>'
        .'<candidates><candidate><name>Jane Example</name><office>councillor</office><status>nominated</status>'
        ."<bio>{$bio}</bio><bio_source_url>https://example.com/bio</bio_source_url><notes>{$notes}</notes>"
        .'<entries>'.implode('', $facts).'</entries>'
        .'<planks><plank><key>suites</key><title>More suites</title><topic>housing</topic><tier>top</tier><rank>1</rank><rationale>Her first priority.</rationale>'
        .'<entries>'.feedEntry('plank', 'housing', 'Allow suites.', 'https://example.com/suites').'</entries></plank></planks>'
        .'</candidate></candidates>'
        .'<articles><article><title>Race begins</title><url>https://example.com/story</url><candidates><candidate>Jane Example</candidate></candidates></article></articles>'
        .'<events><event><title>Forum</title><kind>forum</kind><starts_at>2026-10-07T19:00</starts_at></event></events>'
        .'<pulse><taken_on>2026-10-05</taken_on><threads_read>1</threads_read><commenters>2</commenters><issues><issue><key>homes</key><title>Homes</title><topic>housing</topic><voices>2</voices><heat>low</heat><summary>People want homes.</summary></issue></issues></pulse>'
        .'<scorecards><scorecard><key>v4t</key><title>Vote4Tomorrow</title><categories><category><key>b</key><name>Buildings</name><items><item><key>electrify</key><statement>Electrify.</statement></item></items></category></categories>'
        ."<responses><response><candidate>Jane Example</candidate><responded>true</responded><answers><answer item=\"electrify\">{$stance}</answer></answers></response></responses></scorecard></scorecards>";
}

/** The feed as this viewer would fetch it. */
function feedFor(User $user): array
{
    return test()->actingAs($user)->getJson(route('admin.elections.updates'))->assertOk()->json();
}

/** scope => number of items newer than the viewer's last look. */
function feedUnread(array $feed, int $since): array
{
    $unread = [];
    foreach ($feed['candidates'] as $slug => $tabs) {
        foreach ($tabs as $tab => $times) {
            if ($n = count(array_filter($times, fn ($t) => $t > $since))) {
                $unread["{$slug}:{$tab}"] = $n;
            }
        }
    }
    foreach (['events', 'pulse', 'subjects'] as $section) {
        if ($n = count(array_filter($feed['sections'][$section], fn ($t) => $t > $since))) {
            $unread[$section] = $n;
        }
    }

    return $unread;
}

describe('update feed', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->nine = Carbon::parse('2026-10-06 09:00:00');
        Carbon::setTestNow($this->nine);
        feedImport(feedBase());

        // The viewer looks at everything at 10:00; changes arrive at 12:00.
        $this->lastLook = Carbon::parse('2026-10-06 10:00:00')->getTimestamp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00'));
        $this->unread = fn () => feedUnread(feedFor($this->admin), $this->lastLook);
    });

    afterEach(fn () => Carbon::setTestNow());

    it('shows nothing as new for a viewer who has looked at everything', function () {
        expect(($this->unread)())->toBe([]);
    });

    it('shows nothing as new when the same data is imported again', function () {
        feedImport(feedBase());

        expect(($this->unread)())->toBe([]);
    });

    it('files each kind of new entry under the tab it is shown on', function (string $kind, string $topic, string $tab) {
        feedImport('<candidates><candidate><name>Jane Example</name><entries>'.feedEntry($kind, $topic, 'Something new.', 'https://example.com/new')."</entries></candidate></candidates>");

        expect(($this->unread)())->toBe(["jane-example:{$tab}" => 1]);
    })->with([
        'a career fact' => ['background', 'career', 'about'],
        'a business fact' => ['background', 'business', 'about'],
        'a community fact' => ['background', 'community', 'affiliations'],
        'an affiliation' => ['background', 'affiliation', 'affiliations'],
        'a public service fact' => ['background', 'public_service', 'affiliations'],
        'a past activity' => ['background', 'past_activity', 'affiliations'],
        'a platform statement' => ['plank', 'housing', 'platform'],
        'a statement' => ['statement', 'housing', 'words'],
        'a Q&A answer' => ['qa_answer', 'housing', 'words'],
        'a prior record item' => ['prior_record', 'taxes_budget', 'record'],
        'an endorsement' => ['endorsement', 'other', 'endorsements'],
        'a finance record' => ['finance', 'other', 'finance'],
    ]);

    it('flags the Platform tab for a new plank and its statement', function () {
        feedImport('<candidates><candidate><name>Jane Example</name><planks><plank><key>transit</key><title>Better buses</title><topic>transportation</topic><tier>also</tier><rank>2</rank><rationale>Raised twice.</rationale>'
            .'<entries>'.feedEntry('plank', 'transportation', 'More buses.', 'https://example.com/buses').'</entries></plank></planks></candidate></candidates>');

        expect(($this->unread)())->toBe(['jane-example:platform' => 2]);
    });

    it('flags the Platform tab when an existing plank gains a plan', function () {
        feedImport('<candidates><candidate><name>Jane Example</name><planks><plank><key>suites</key><title>More suites</title><topic>housing</topic><tier>top</tier><rank>1</rank><rationale>Her first priority.</rationale>'
            .'<plan status="partial"><summary>Rezone lots.</summary><detail aspect="how" source_url="https://example.com/suites">Rezone lots.</detail></plan></plank></planks></candidate></candidates>');

        expect(($this->unread)())->toBe(['jane-example:platform' => 1]);
    });

    it('flags the Platform tab when a plank is re-ranked', function () {
        feedImport('<candidates><candidate><name>Jane Example</name><planks><plank><key>suites</key><title>More suites</title><topic>housing</topic><tier>also</tier><rank>1</rank><rationale>Now her second priority.</rationale></plank></planks></candidate></candidates>');

        expect(($this->unread)())->toBe(['jane-example:platform' => 1]);
    });

    it('flags the news tab when a story is linked to a candidate', function () {
        feedImport('<articles><article><title>Forum recap</title><url>https://example.com/recap</url><candidates><candidate>Jane Example</candidate></candidates></article></articles>');

        expect(($this->unread)())->toBe(['jane-example:news' => 1]);
    });

    it('flags the scorecard tab when a stance changes', function () {
        feedImport('<scorecards><scorecard><key>v4t</key><title>Vote4Tomorrow</title><categories><category><key>b</key><name>Buildings</name><items><item><key>electrify</key><statement>Electrify.</statement></item></items></category></categories>'
            .'<responses><response><candidate>Jane Example</candidate><responded>true</responded><answers><answer item="electrify">opposed</answer></answers></response></responses></scorecard></scorecards>');

        expect(($this->unread)())->toBe(['jane-example:scorecards' => 1]);
    });

    it('flags About when the profile changes, but not for a notes-only change', function () {
        feedImport('<candidates><candidate><name>Jane Example</name><bio>Jane is a teacher and coach.</bio><bio_source_url>https://example.com/bio</bio_source_url></candidate></candidates>');
        expect(($this->unread)())->toBe(['jane-example:about' => 1]);
    });

    it('keeps a notes-only change off About and shows it on the admin-only notes tab', function () {
        feedImport('<candidates><candidate><name>Jane Example</name><notes>Checked twice. Then a third time.</notes></candidate></candidates>');

        expect(($this->unread)())->toBe(['jane-example:notes' => 1]);

        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();
        $viewerFeed = feedFor($viewer->fresh());
        expect($viewerFeed['candidates']['jane-example'])->not->toHaveKey('notes')
            ->and(feedUnread($viewerFeed, $this->lastLook))->toBe([]);
    });

    it('lights up a brand new candidate', function () {
        feedImport('<candidates><candidate><name>Sam Sample</name><office>councillor</office><status>declared</status><entries>'.feedEntry('background', 'career', 'Plumber.', 'https://example.com/sam').'</entries></candidate></candidates>');

        expect(($this->unread)())->toBe(['sam-sample:about' => 2]);
    });

    it('flags an added election event', function () {
        feedImport('<events><event><title>Advance voting</title><kind>advance_voting</kind><starts_at>2026-10-13T08:00</starts_at></event></events>');

        expect(($this->unread)())->toBe(['events' => 1]);
    });

    it('flags Community Pulse for a new or replaced snapshot', function () {
        feedImport('<pulse><taken_on>2026-10-05</taken_on><threads_read>6</threads_read><commenters>80</commenters><issues><issue><key>homes</key><title>Homes</title><topic>housing</topic><voices>9</voices><heat>high</heat><summary>More homes.</summary></issue></issues></pulse>');

        expect(($this->unread)())->toBe(['pulse' => 1]);
    });

    it('flags Browse by subject for a new tag', function () {
        feedImport('<vocabulary><tag><slug>transit</slug><topic>transportation</topic><name>Transit</name></tag></vocabulary>');

        expect(($this->unread)())->toBe(['subjects' => 1]);
    });

    it('flags Browse by subject when a record is newly tagged', function () {
        feedImport('<candidates><candidate><name>Jane Example</name><entries><entry><kind>statement</kind><topic>housing</topic><summary>Wants more suites.</summary><source_url>https://example.com/statement</source_url><source_type>candidate_site</source_type><tags><tag>housing</tag></tags></entry></entries></candidate></candidates>');

        expect(($this->unread)())->toBe(['subjects' => 1]);
    });

    it('dates each City plan sheet by its read_on date', function () {
        $feed = feedFor($this->admin);

        foreach (CityPlans::all() as $plan) {
            expect($feed['sections']['plans'][$plan['slug']])
                ->toBe(Carbon::parse($plan['read_on'], config('app.timezone'))->startOfDay()->getTimestamp());
        }
    });

    it('shares its tab mapping with the candidate page', function () {
        $portfolio = app(BuildCandidatePortfolio::class)->handle(Candidate::where('name', 'Jane Example')->first());

        $shown = [
            'about' => collect($portfolio['background'])->flatMap(fn ($g) => $g['entries']),
            'affiliations' => collect($portfolio['affiliations'])->flatMap(fn ($g) => $g['entries']),
            'platform' => collect($portfolio['platform']['tiers'])->flatMap(fn ($t) => $t['planks'])->flatMap(fn ($p) => $p['sources'])->concat($portfolio['platform']['unranked']),
        ];
        foreach ($portfolio['sections'] as $section) {
            $shown[$section['key']] = collect($section['groups'])->flatMap(fn ($g) => $g['entries']);
        }

        expect($shown['about'])->not->toBeEmpty();
        foreach ($shown as $tab => $entries) {
            foreach ($entries as $entry) {
                expect($entry['tab'])->toBe($tab);
            }
        }
    });

    it('gives the candidate page the timestamps its tabs compare against the viewer\'s last look', function () {
        $slug = Candidate::where('name', 'Jane Example')->value('slug');

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', $slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('portfolio.candidate.profile_changed_at', fn ($v) => is_string($v))
                ->where('portfolio.candidate.notes_changed_at', fn ($v) => is_string($v))
                ->where('portfolio.scorecards.0.changed_at', fn ($v) => is_string($v))
                ->where('portfolio.platform.tiers.0.planks.0.changed_at', fn ($v) => is_string($v))
                ->where('portfolio.articles.0.linked_at', fn ($v) => is_string($v))
                ->where('portfolio.background.0.entries.0.tab', 'about'));

        // Invited viewers never get the notes timestamp.
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();
        $this->actingAs($viewer->fresh())
            ->get(route('admin.elections.candidates.show', $slug))
            ->assertInertia(fn ($page) => $page->missing('portfolio.candidate.notes_changed_at'));
    });

    it('lets invited viewers fetch the feed and keeps everyone else out', function () {
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())->getJson(route('admin.elections.updates'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'customer']))->getJson(route('admin.elections.updates'))->assertForbidden();
    });
});
