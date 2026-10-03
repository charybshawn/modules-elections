<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Http\Resources\ArticleResource;
use Cultpantry\Elections\Http\Resources\PlankResource;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\ScorecardAnswer;
use Cultpantry\Elections\Models\ScorecardItem;
use Cultpantry\Elections\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * One tag across the whole module: where each candidate stands on it (their
 * tagged planks, in their own emphasis tiers, plus their stance on any
 * tagged scorecard statement), who has nothing on file for it, what
 * residents are raising under it, the news, and the tags that most often
 * appear alongside it.
 */
class BuildTagPage
{
    public function handle(Tag $tag): array
    {
        $planks = $tag->planks()->with(['candidate', 'entries'])->get();
        $items = $tag->scorecardItems()->with('category.scorecard')->get();

        $answers = ScorecardAnswer::with('response')
            ->whereIn('item_id', $items->pluck('id'))
            ->whereHas('response', fn ($q) => $q->where('responded', true))
            ->get()
            ->groupBy(fn (ScorecardAnswer $a) => $a->response->candidate_id);

        $candidates = Candidate::where('status', '!=', 'withdrawn')->orderBy('office')->orderBy('name')->get();
        $itemKey = fn (ScorecardItem $i) => $i->category->scorecard->key.'/'.$i->key;
        $itemById = $items->keyBy('id');

        $positions = $candidates->map(function (Candidate $candidate) use ($planks, $answers, $itemById, $itemKey) {
            $own = $planks->where('candidate_id', $candidate->id)
                ->sortBy(fn (Plank $p) => [array_search($p->tier, array_keys(Plank::TIERS), true), $p->rank])
                ->map(fn (Plank $p) => [
                    'key' => $p->key,
                    'title' => $p->title,
                    'summary' => $p->summary,
                    'tier' => $p->tier,
                    'rank' => $p->rank,
                    'analysis' => PlankResource::make($p)->resolve()['analysis'],
                ])->values()->all();
            $stances = ($answers->get($candidate->id) ?? collect())
                ->mapWithKeys(fn (ScorecardAnswer $a) => [$itemKey($itemById[$a->item_id]) => $a->stance])
                ->all();

            return [
                'name' => $candidate->name,
                'slug' => $candidate->slug,
                'office' => $candidate->office,
                'planks' => $own,
                'stances' => $stances,
                // Their strongest emphasis on this tag, for ordering the list.
                'best_tier' => $own[0]['tier'] ?? null,
            ];
        });

        $tierOrder = array_flip(array_keys(Plank::TIERS));
        $withPosition = $positions->filter(fn ($p) => $p['planks'] !== [] || $p['stances'] !== [])
            ->sortBy(fn ($p) => [$p['best_tier'] === null ? 9 : $tierOrder[$p['best_tier']], $p['name']])
            ->values();

        return [
            'tag' => [
                'slug' => $tag->slug,
                'name' => $tag->name,
                'topic' => $tag->topic,
                'heading' => Entry::TOPICS[$tag->topic] ?? $tag->topic,
                'description' => $tag->description,
            ],
            'positions' => $withPosition->all(),
            'nothingOnFile' => $positions->filter(fn ($p) => $p['planks'] === [] && $p['stances'] === [])->map(fn ($p) => ['name' => $p['name'], 'slug' => $p['slug']])->values()->all(),
            'scorecardItems' => $items->map(fn (ScorecardItem $i) => [
                'id' => $itemKey($i),
                'scorecard' => $i->category->scorecard->title,
                'category' => $i->category->name,
                'statement' => $i->statement,
                'field' => ScorecardAnswer::where('item_id', $i->id)
                    ->whereHas('response', fn ($q) => $q->where('responded', true))
                    ->selectRaw('stance, count(*) as total')->groupBy('stance')->pluck('total', 'stance')
                    ->map(fn ($n) => (int) $n)->all(),
            ])->values()->all(),
            'pulseIssues' => $tag->pulseIssues()->with('snapshot')->get()
                ->sortByDesc(fn ($i) => $i->snapshot->taken_on)
                ->map(fn ($i) => [
                    'key' => $i->key,
                    'title' => $i->title,
                    'voices' => $i->voices,
                    'heat' => $i->heat,
                    'summary' => $i->summary,
                    'taken_on' => $i->snapshot->taken_on->toDateString(),
                ])->values()->all(),
            'articles' => ArticleResource::collection($tag->articles()->orderByDesc('published_on')->get())->resolve(),
            'related' => $this->related($tag),
        ];
    }

    /**
     * Tags that most often sit on the same records as this one -- what this
     * subject tends to be discussed together with.
     */
    private function related(Tag $tag): array
    {
        $same = DB::table('elections_taggables as mine')
            ->join('elections_taggables as other', function ($join) {
                $join->on('other.taggable_type', '=', 'mine.taggable_type')
                    ->on('other.taggable_id', '=', 'mine.taggable_id')
                    ->whereColumn('other.tag_id', '!=', 'mine.tag_id');
            })
            ->where('mine.tag_id', $tag->id)
            ->selectRaw('other.tag_id, count(*) as together')
            ->groupBy('other.tag_id')
            ->orderByDesc('together')
            ->limit(6)
            ->pluck('together', 'other.tag_id');

        $tags = Tag::whereIn('id', $same->keys())->get()->keyBy('id');

        return $same->map(fn ($together, $id) => [
            'slug' => $tags[$id]->slug,
            'name' => $tags[$id]->name,
            'together' => (int) $together,
        ])->values()->all();
    }
}
