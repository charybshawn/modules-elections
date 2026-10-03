<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Plank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

/**
 * Planks only arrive by XML import; this is how a wrong one comes off a
 * candidate's platform -- together with the statements filed under it.
 */
class PlankController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function destroy(Candidate $candidate, Plank $plank): RedirectResponse
    {
        abort_unless($plank->candidate_id === $candidate->id, 404);
        $this->authorize('delete', $plank);

        DB::transaction(function () use ($plank) {
            $plank->entries()->delete();
            $plank->delete();
        });

        return redirect()
            ->route('admin.elections.candidates.show', ['candidate' => $candidate, 'tab' => 'platform'])
            ->with('success', 'Plank deleted.');
    }
}
