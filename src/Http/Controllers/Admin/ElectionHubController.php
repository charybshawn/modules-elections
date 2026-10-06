<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildElectionDashboard;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Elections landing page, which picks between the Salmon Arm municipal
 * election (the existing dashboard) and the BC provincial election, plus the
 * provincial page, a placeholder until there's research to show.
 */
class ElectionHubController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function home(BuildElectionDashboard $buildElectionDashboard): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Home', [
            'salmonArm' => [
                'candidates' => Candidate::where('status', '!=', 'withdrawn')->count(),
                'votingDay' => ElectionEvent::where('kind', 'general_voting')->orderBy('starts_at')->value('starts_at')?->toIso8601String(),
                // What the dashboard's "new since your last visit" count needs;
                // the count itself is worked out in the browser from the
                // viewer's seen-cookie (see Partials/seen.ts).
                'unread' => [
                    'candidates' => Candidate::get(['id', 'slug', 'updated_at'])
                        ->map(fn (Candidate $c) => ['id' => $c->id, 'slug' => $c->slug, 'updated_at' => $c->updated_at?->toIso8601String()])
                        ->all(),
                    'activity' => $buildElectionDashboard->activity(),
                    'now' => now()->getTimestamp(),
                ],
            ],
        ]);
    }

    public function provincial(): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Provincial');
    }
}
