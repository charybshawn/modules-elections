<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\ChangelogEntry;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\PartyArticle;
use Cultpantry\Elections\Models\PartyClaim;
use Cultpantry\Elections\Models\Poll;
use Cultpantry\Elections\Models\RidingProjection;
use Illuminate\Support\Carbon;

/**
 * Everything the update email says, for both elections: one section each,
 * the one whose voting day is nearest first, and only for an election that
 * has something new. A section is the same headline digest the municipal
 * email always carried (see BuildUpdateDigest), and the provincial one adds
 * what only it has: new polls and how the poll average moved, the model's
 * projection for this riding, new fact-checks and party-level news. After
 * the sections comes the site change log: the entries whose commit time falls
 * between the last email and this one, biggest effect on the user experience first.
 */
class BuildElectionsEmail
{
    /** How many new polls, fact-checks and party stories the provincial section lists before counting the rest. */
    public const POLLS = 4;

    public const FACT_CHECKS = 5;

    public const PARTY_NEWS = 4;

    public function __construct(private BuildUpdateDigest $digests, private BuildPollAverage $polls) {}

    /**
     * @return array{total: int, since: string, until: string, voting_days: list<array<string, mixed>>, sections: list<array<string, mixed>>, changelog: list<array{title: string, body: string, scope: string, scope_label: string, committed_at: string}>}
     */
    public function handle(Carbon $since, Carbon $until): array
    {
        $sections = collect([$this->section(Election::municipal(), $since, $until), $this->section(Election::provincial(), $since, $until)])
            ->filter(fn (array $s) => $s['total'] > 0)
            // Soonest voting day first; one with no date (or already past) goes last.
            ->sortBy(fn (array $s) => $s['days_away'] === null || $s['days_away'] < 0 ? PHP_INT_MAX : $s['days_away'])
            ->values();

        $changelog = ChangelogEntry::where('committed_at', '>', $since)->where('committed_at', '<=', $until)
            ->orderByDesc('impact')->orderByDesc('committed_at')->orderByDesc('id')->get()
            ->map(fn (ChangelogEntry $e) => [
                'title' => $e->title,
                'body' => $e->body,
                'scope' => $e->scope,
                'scope_label' => ChangelogEntry::SCOPES[$e->scope] ?? $e->scope,
                'page' => $e->page,
                'page_label' => ChangelogEntry::PAGES[$e->page] ?? $e->page,
                'impact' => $e->impact,
                'impact_label' => ChangelogEntry::IMPACTS[$e->impact] ?? '',
                'committed_at' => $e->committed_at->toIso8601String(),
                'url' => $e->url,
            ])->all();

        return [
            'total' => $sections->sum('total') + count($changelog),
            'since' => $since->toIso8601String(),
            'until' => $until->toIso8601String(),
            'voting_days' => $this->votingDays(),
            'sections' => $sections->all(),
            'changelog' => $changelog,
        ];
    }

    private function section(Election $election, Carbon $since, Carbon $until): array
    {
        $digest = $this->digests->handle($since, $until, $election);
        $provincial = $election->isProvincial() ? $this->provincial($election, $since, $until) : null;

        $votingDay = ElectionEvent::forElection($election)->where('kind', 'general_voting')->orderBy('starts_at')->value('starts_at');
        $tz = config('app.timezone');

        return [
            'key' => $election->isProvincial() ? 'provincial' : 'municipal',
            'title' => $election->isProvincial() ? 'BC provincial election' : 'Salmon Arm municipal election',
            'subtitle' => $election->isProvincial() ? $election->riding : 'Mayor, council and school board',
            'url' => route($election->isProvincial() ? 'admin.elections.provincial' : 'admin.elections.index'),
            'voting_day' => $votingDay?->timezone($tz)->toIso8601String(),
            'days_away' => $votingDay ? (int) now($tz)->startOfDay()->diffInDays($votingDay->copy()->timezone($tz)->startOfDay(), false) : null,
            'digest' => $digest,
            'provincial' => $provincial,
            'total' => $digest['total'] + ($provincial['total'] ?? 0),
        ];
    }

    /** What only the provincial election has. */
    private function provincial(Election $election, Carbon $since, Carbon $until): array
    {
        $window = fn ($query) => $query->where('created_at', '>', $since)->where('created_at', '<=', $until);

        $newPolls = $window(Poll::query())->orderByDesc('fielded_to')->orderByDesc('id')->get();
        $newClaims = $window(PartyClaim::query())->with('party:id,name,slug')->orderBy('id')->get();
        $newNews = $window(PartyArticle::query())->with('party:id,name,slug')->orderByDesc('published_on')->orderByDesc('id')->get();

        $allPolls = Poll::all();
        $now = $this->polls->average($this->polls->current($allPolls));
        // The same average as it stood before this window, so each party's movement can be shown.
        $before = $this->polls->average($this->polls->current($allPolls->filter(fn (Poll $p) => $p->created_at <= $since)));
        $average = $now === null ? null : $now + ['change' => collect($now['parties'])->map(
            fn (array $a, string $party) => $before === null || ! isset($before['parties'][$party]) ? null : round($a['percent'] - $before['parties'][$party]['percent'], 1)
        )->all()];

        $projection = RidingProjection::where('riding', $election->riding)->first();
        $leaders = RidingProjection::all()->countBy('leader')->sortDesc();

        $ratings = PartyClaim::RATINGS;

        return [
            'total' => $newPolls->count() + $newClaims->count() + $newNews->count(),
            'more_polls' => max(0, $newPolls->count() - self::POLLS),
            'polls' => $newPolls->take(self::POLLS)->map(fn (Poll $p) => [
                'pollster' => $p->pollster,
                'ended' => $p->fielded_to->toDateString(),
                'sample_size' => $p->sample_size,
                'results' => collect($p->results)->sortDesc()->all(),
                'url' => $p->source_url,
            ])->all(),
            'average' => $average,
            'projection' => $projection ? [
                'riding' => $projection->riding,
                'rating' => $projection->rating,
                'shares' => $projection->shares,
                'as_of' => $projection->as_of->toDateString(),
                'url' => $projection->source_url,
                // How the model has the whole province split, for one line of context.
                'leaders' => $leaders->all(),
            ] : null,
            'more_fact_checks' => max(0, $newClaims->count() - self::FACT_CHECKS),
            'fact_checks' => $newClaims->take(self::FACT_CHECKS)->map(fn (PartyClaim $c) => [
                'party' => $c->party->name,
                'claim' => $c->claim,
                'rating' => $c->rating,
                'rating_label' => $ratings[$c->rating] ?? $c->rating,
                'url' => route('admin.elections.provincial.parties.show', ['party' => $c->party->slug, 'tab' => 'fact-check']),
            ])->all(),
            'more_party_news' => max(0, $newNews->count() - self::PARTY_NEWS),
            'party_news' => $newNews->take(self::PARTY_NEWS)->map(fn (PartyArticle $a) => [
                'party' => $a->party->name,
                'title' => $a->title,
                'url' => $a->url,
                'outlet' => $a->outlet,
                'text' => $a->summary,
            ])->all(),
        ];
    }

    /** Both elections' voting days, soonest first, for the countdowns in the email's header (shown whether or not a section is). */
    private function votingDays(): array
    {
        $tz = config('app.timezone');

        return collect([Election::municipal(), Election::provincial()])->map(function (Election $e) use ($tz) {
            $day = ElectionEvent::forElection($e)->where('kind', 'general_voting')->orderBy('starts_at')->value('starts_at');

            return [
                'key' => $e->isProvincial() ? 'provincial' : 'municipal',
                'label' => $e->isProvincial() ? 'BC provincial' : 'Salmon Arm',
                'voting_day' => $day?->timezone($tz)->toIso8601String(),
                'days_away' => $day ? (int) now($tz)->startOfDay()->diffInDays($day->copy()->timezone($tz)->startOfDay(), false) : null,
            ];
        })->sortBy(fn (array $d) => $d['days_away'] === null || $d['days_away'] < 0 ? PHP_INT_MAX : $d['days_away'])->values()->all();
    }
}
