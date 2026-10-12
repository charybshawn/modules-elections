<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildPartyPage;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A provincial party's own page: its profile, candidates, news and the
 * fact-check record. Read-only, like the rest of the module; the data
 * arrives through the XML import.
 */
class PartyController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function show(Request $request, Party $party, BuildPartyPage $buildPartyPage): Response
    {
        $this->authorize('viewAny', Candidate::class);

        $tab = $request->query('tab');

        return Inertia::render('Vendor/elections/Provincial/Party', $buildPartyPage->handle($party) + [
            // Each tab is its own URL (?tab=), as on the candidate page.
            'activeTab' => in_array($tab, ['news', 'fact-check'], true) ? $tab : 'about',
        ]);
    }
}
