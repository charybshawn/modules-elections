<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * The quick-search (⌘K) index: candidates, subjects and planks, small
 * enough to send whole and match in the browser. Fetched the first time
 * the search opens, not with every page.
 */
class SearchIndexController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', Candidate::class);

        return response()->json([
            'candidates' => Candidate::orderBy('name')->get(['name', 'slug', 'office', 'occupation', 'status'])
                ->map(fn (Candidate $c) => $c->only(['name', 'slug', 'office', 'occupation', 'status'])),
            'subjects' => Tag::orderBy('name')->get(['slug', 'name', 'topic'])
                ->map(fn (Tag $t) => ['slug' => $t->slug, 'name' => $t->name, 'heading' => Entry::TOPICS[$t->topic] ?? $t->topic]),
            'planks' => Plank::with('candidate:id,name,slug,status')->get(['key', 'title', 'tier', 'candidate_id'])
                ->filter(fn (Plank $p) => $p->candidate !== null)
                ->map(fn (Plank $p) => [
                    'key' => $p->key,
                    'title' => $p->title,
                    'tier' => $p->tier,
                    'candidate' => $p->candidate->name,
                    'candidate_slug' => $p->candidate->slug,
                ])
                ->values(),
        ]);
    }
}
