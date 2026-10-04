<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildCandidateComparison;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Candidates side by side: /admin/elections/compare?c[]=slug&c[]=slug.
 * The selection lives in the URL so a comparison can be bookmarked or
 * shared, and Back returns to it.
 */
class CompareController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function show(Request $request, BuildCandidateComparison $buildCandidateComparison): Response
    {
        $this->authorize('viewAny', Candidate::class);

        $slugs = collect((array) $request->query('c', []))
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->unique()
            ->take(BuildCandidateComparison::MAX)
            ->values();

        // Keep the picked order, not the database's.
        $picked = Candidate::whereIn('slug', $slugs)->get()->sortBy(fn (Candidate $c) => $slugs->search($c->slug))->values();
        foreach ($picked as $candidate) {
            $this->authorize('view', $candidate);
        }

        $everyone = Candidate::where('status', '!=', 'withdrawn')
            ->orderByRaw(Candidate::OFFICE_ORDER_SQL)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'office']);

        return Inertia::render('Vendor/elections/Compare', [
            ...$buildCandidateComparison->handle($picked),
            'everyone' => $everyone->map(fn (Candidate $c) => ['slug' => $c->slug, 'name' => $c->name, 'office' => $c->office])->all(),
            'max' => BuildCandidateComparison::MAX,
            'options' => Options::all(),
        ]);
    }
}
