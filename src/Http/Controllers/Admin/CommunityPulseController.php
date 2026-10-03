<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildCommunityPulse;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\PulseIssue;
use Cultpantry\Elections\Models\PulseSnapshot;
use Cultpantry\Elections\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Cultpantry\Elections\Models\Tag;

/**
 * The Community Pulse page: our summary of what residents are discussing,
 * kept apart from the sourced candidate portfolios. Visible to invited
 * viewers; deleting a snapshot is admin-only.
 */
class CommunityPulseController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function index(Request $request, BuildCommunityPulse $buildCommunityPulse): Response
    {
        $this->authorize('viewAny', PulseSnapshot::class);

        $date = $request->query('date');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;

        return Inertia::render('Vendor/elections/Pulse', [
            ...$buildCommunityPulse->handle($date),
            'heat' => PulseIssue::HEAT,
            'options' => Options::all(),
        ]);
    }

    public function destroy(PulseSnapshot $snapshot): RedirectResponse
    {
        $this->authorize('delete', $snapshot);

        $snapshot->delete();
        Tag::pruneOrphans();

        return redirect()->route('admin.elections.pulse.index')->with('success', 'Pulse snapshot deleted.');
    }
}
