<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\ElectionEvent;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Elections landing page, which picks between the Salmon Arm municipal
 * election and the BC provincial election (each with its own dashboard).
 */
class ElectionHubController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function home(): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Home', [
            'salmonArm' => $this->summary(Election::municipal()),
            'provincial' => $this->summary(Election::provincial()),
        ]);
    }

    /** @return array{candidates: int, votingDay: string|null} */
    private function summary(Election $election): array
    {
        return [
            'candidates' => Candidate::forElection($election)->where('status', '!=', 'withdrawn')->count(),
            'votingDay' => ElectionEvent::forElection($election)->where('kind', 'general_voting')->orderBy('starts_at')->value('starts_at')?->toIso8601String(),
        ];
    }
}
