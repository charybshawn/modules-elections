<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Support\CityFinances;
use Cultpantry\Elections\Support\CityPlans;
use Cultpantry\Elections\Support\PlanProgress;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Elections -> City plans: one-page info sheets on the City plans council
 * works from (see Support\CityPlans for the content and its sources).
 * Read-only, so invited viewers see them too.
 */
class CityPlanController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function index(): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Plans/Index', [
            'plans' => array_values(array_map(
                fn (array $plan) => array_intersect_key($plan, array_flip(['slug', 'title', 'short', 'status', 'stats'])),
                CityPlans::all(),
            )),
            'finances' => array_intersect_key(CityFinances::sheet(), array_flip(['title', 'subtitle', 'stats'])) + ['slug' => CityFinances::SLUG],
            'progress' => array_values(array_map(fn (array $sheet) => PlanProgress::card(), PlanProgress::all())),
        ]);
    }

    public function show(string $plan): Response
    {
        $this->authorize('viewAny', Candidate::class);

        $sheet = CityPlans::find($plan);
        abort_if($sheet === null, 404);

        return Inertia::render('Vendor/elections/Plans/Show', [
            'plan' => $sheet,
            'progressSlug' => PlanProgress::find($plan.'-progress') ? $plan.'-progress' : null,
        ]);
    }

    public function progress(string $slug): Response
    {
        $this->authorize('viewAny', Candidate::class);

        $sheet = PlanProgress::find($slug);
        abort_if($sheet === null, 404);

        return Inertia::render('Vendor/elections/Plans/Progress', ['sheet' => $sheet]);
    }
}
