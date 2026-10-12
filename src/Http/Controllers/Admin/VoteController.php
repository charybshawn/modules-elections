<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Tag;
use Cultpantry\Elections\Models\Vote;
use Cultpantry\Elections\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Votes only arrive by XML import; this is how a wrong one is removed.
 */
class VoteController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function destroy(Candidate $candidate, Vote $vote): RedirectResponse
    {
        abort_unless($vote->candidate_id === $candidate->id, 404);
        $this->authorize('delete', $vote);

        $vote->delete();
        Tag::pruneOrphans();
        Audit::record('elections.vote_deleted', "Vote deleted from {$candidate->name}: {$vote->title}", $vote, [
            'candidate' => $candidate->name, 'voted_on' => $vote->voted_on?->toDateString(), 'source_url' => $vote->source_url,
        ], 'warning');

        return redirect()->route('admin.elections.candidates.show', ['candidate' => $candidate, 'tab' => 'record'])->with('success', 'Vote deleted.');
    }
}
