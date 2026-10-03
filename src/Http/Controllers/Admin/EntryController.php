<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Cultpantry\Elections\Models\Tag;

/**
 * Entries only arrive by XML import; this is how a wrong or irrelevant one
 * gets removed from a candidate's page.
 */
class EntryController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function destroy(Candidate $candidate, Entry $entry): RedirectResponse
    {
        abort_unless($entry->candidate_id === $candidate->id, 404);
        $this->authorize('delete', $entry);

        $entry->delete();
        Tag::pruneOrphans();

        return redirect()->route('admin.elections.candidates.show', $candidate)->with('success', 'Entry deleted.');
    }
}
