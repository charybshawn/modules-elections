<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildPollAverage;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Election;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The province-wide poll history: every poll on file as a chart with a
 * smoothed trend per party. Read-only; the polls arrive through the XML import.
 */
class PollController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    /** The day the election was called (Premier Eby's announcement), marked on the chart. */
    public const CALLED_ON = '2026-09-22';

    public function index(BuildPollAverage $buildPollAverage): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Provincial/Polls', $buildPollAverage->handle() + [
            'election' => Election::provincial()->toPage(),
            'calledOn' => self::CALLED_ON,
            'votingDay' => '2026-10-24',
        ]);
    }
}
