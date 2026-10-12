<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ChangelogEntry;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site's change log: what changed, in a sentence or two each, newest first.
 * Read-only; the entries are written by hand and arrive through the XML import.
 */
class ChangelogController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function index(): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Changelog', [
            'entries' => ChangelogEntry::orderByDesc('committed_at')->orderByDesc('id')->get()->map(fn (ChangelogEntry $e) => [
                'id' => $e->id,
                'day' => $e->committed_at->timezone(config('app.timezone'))->toDateString(),
                'title' => $e->title,
                'body' => $e->body,
                'scope' => $e->scope,
                'page' => $e->page,
                'impact' => $e->impact,
                'url' => $e->url,
            ])->all(),
            'scopes' => ChangelogEntry::SCOPES,
            'pages' => ChangelogEntry::PAGES,
            'impacts' => ChangelogEntry::IMPACTS,
        ]);
    }
}
