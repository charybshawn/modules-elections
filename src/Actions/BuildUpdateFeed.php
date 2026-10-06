<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Support\CityPlans;
use Cultpantry\Elections\Support\PlanProgress;
use Cultpantry\Elections\Support\UpdateTabs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything that can count as "new" to a viewer, as plain timestamps, so the
 * browser can compare each against when that viewer last looked at it (kept
 * per viewer in the browser; see resources/js/Pages/Partials/updates.ts).
 *
 *   candidates[slug][tab]   Unix times of items on that candidate-page tab:
 *                           entries added, planks added or changed, news
 *                           stories linked, scorecard changes, profile
 *                           changes (About) and, for admins, notes changes.
 *   sections.events         election events added or changed
 *   sections.pulse          Community Pulse snapshots added or replaced
 *   sections.subjects       tags and tagged records added
 *   sections.plans[slug]    when each City plan sheet was last refreshed
 *
 * One item is one thing to read, so counts mean something: a plank changed
 * five times is one item, ten new entries are ten.
 */
class BuildUpdateFeed
{
    public function handle(bool $includeNotes): array
    {
        $candidates = [];
        $add = function (?int $candidateId, ?string $tab, mixed $at) use (&$candidates, &$slugs) {
            $slug = $slugs[$candidateId] ?? null;
            $time = $this->unix($at);
            if ($slug === null || $tab === null || $time === null) {
                return;
            }
            $candidates[$slug][$tab][] = $time;
        };

        $slugs = [];
        foreach (DB::table('elections_candidates')->get(['id', 'slug', 'created_at', 'profile_changed_at', 'notes_changed_at', 'notes']) as $row) {
            $slugs[$row->id] = $row->slug;
            // Every candidate has an About tab, so a profile change (or a new candidate) lands there.
            $add($row->id, 'about', $this->latest($row->created_at, $row->profile_changed_at));
            if ($includeNotes && $row->notes !== null && $row->notes !== '') {
                $add($row->id, 'notes', $this->latest($row->created_at, $row->notes_changed_at));
            }
        }

        foreach (DB::table('elections_entries')->get(['candidate_id', 'kind', 'topic', 'created_at']) as $row) {
            $add($row->candidate_id, UpdateTabs::forEntry($row->kind, $row->topic), $row->created_at);
        }

        foreach (DB::table('elections_planks')->get(['candidate_id', 'created_at', 'updated_at']) as $row) {
            $add($row->candidate_id, 'platform', $this->latest($row->created_at, $row->updated_at));
        }

        foreach (DB::table('elections_article_candidate')->whereNotNull('created_at')->get(['candidate_id', 'created_at']) as $row) {
            $add($row->candidate_id, 'news', $row->created_at);
        }

        $answers = DB::table('elections_scorecard_answers')->selectRaw('response_id, max(updated_at) as at')->groupBy('response_id')->pluck('at', 'response_id');
        $takeaways = DB::table('elections_scorecard_takeaways')->selectRaw('response_id, max(updated_at) as at')->groupBy('response_id')->pluck('at', 'response_id');
        $categories = DB::table('elections_scorecard_categories')->selectRaw('scorecard_id, max(updated_at) as at')->groupBy('scorecard_id')->pluck('at', 'scorecard_id');
        foreach (DB::table('elections_scorecard_responses')->get(['id', 'candidate_id', 'scorecard_id', 'created_at', 'updated_at']) as $row) {
            $add($row->candidate_id, 'scorecards', $this->latest($row->created_at, $row->updated_at, $answers[$row->id] ?? null, $takeaways[$row->id] ?? null, $categories[$row->scorecard_id] ?? null));
        }

        foreach ($candidates as &$tabs) {
            foreach ($tabs as &$times) {
                sort($times);
            }
        }
        unset($tabs, $times);

        return [
            'now' => now()->getTimestamp(),
            'candidates' => $candidates,
            'sections' => [
                'events' => $this->times(DB::table('elections_events')->get(['created_at', 'updated_at']), fn ($r) => $this->latest($r->created_at, $r->updated_at)),
                'pulse' => $this->pulse(),
                'subjects' => $this->subjects(),
                'plans' => $this->plans(),
            ],
        ];
    }

    /**
     * One time per snapshot: when it was added or last changed. The importer
     * only touches a snapshot when its issues or mentions really changed, so a
     * re-import of the same content isn't an update.
     */
    private function pulse(): array
    {
        return $this->times(DB::table('elections_pulse_snapshots')->get(['created_at', 'updated_at']), fn ($r) => $this->latest($r->created_at, $r->updated_at));
    }

    /**
     * New tags and newly tagged records. Only the distinct times are sent (an
     * import tags hundreds of records in the same second), since the page only
     * needs to know whether something arrived after the viewer's last look.
     */
    private function subjects(): array
    {
        $unique = array_values(array_unique(array_merge(
            $this->times(DB::table('elections_tags')->get(['created_at']), fn ($r) => $r->created_at),
            $this->times(DB::table('elections_taggables')->get(['created_at']), fn ($r) => $r->created_at),
        )));
        sort($unique);

        return $unique;
    }

    /** slug => when the sheet was last refreshed (its `read_on` date, from the start of that day). */
    private function plans(): array
    {
        $plans = [];
        foreach (CityPlans::all() as $plan) {
            $at = $plan['read_on'] ?? null;
            if (isset($plan['slug']) && $at !== null) {
                $plans[$plan['slug']] = Carbon::parse($at, config('app.timezone'))->startOfDay()->getTimestamp();
            }
        }

        foreach (PlanProgress::all() as $sheet) {
            $plans[$sheet['slug']] = Carbon::parse($sheet['read_on'], config('app.timezone'))->startOfDay()->getTimestamp();
        }

        return $plans;
    }

    /** @return list<int> */
    private function times($rows, callable $at): array
    {
        $out = [];
        foreach ($rows as $row) {
            $time = $this->unix($at($row));
            if ($time !== null) {
                $out[] = $time;
            }
        }
        sort($out);

        return $out;
    }

    private function latest(mixed ...$values): mixed
    {
        $times = array_filter(array_map(fn ($v) => $this->unix($v), $values));

        return $times === [] ? null : max($times);
    }

    private function unix(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->getTimestamp() : Carbon::parse($value)->getTimestamp();
    }
}
