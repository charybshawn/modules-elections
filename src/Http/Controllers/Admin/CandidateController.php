<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Actions\BuildCandidatePortfolio;
use Cultpantry\Elections\Actions\BuildElectionDashboard;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Tag;
use Cultpantry\Elections\Support\Audit;
use Cultpantry\Elections\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard, candidate portfolios, and the XML import/export that is
 * the only way data gets in -- there are no create/edit forms.
 */
class CandidateController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function index(BuildElectionDashboard $buildElectionDashboard): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Index', [
            ...$buildElectionDashboard->handle(Election::municipal()),
            'options' => Options::all(),
        ]);
    }

    /** The BC provincial election's dashboard: its own page (Provincial/Index), scoped to the riding. */
    public function provincial(BuildElectionDashboard $buildElectionDashboard): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Provincial/Index', [
            ...$buildElectionDashboard->handle(Election::provincial()),
            'options' => Options::all(),
        ]);
    }

    public function show(Request $request, Candidate $candidate, BuildCandidatePortfolio $buildCandidatePortfolio): Response
    {
        $this->authorize('view', $candidate);

        $portfolio = $buildCandidatePortfolio->handle($candidate);

        // Each tab is its own URL (?tab=platform), like Customers' Overview /
        // Support tabs; an unknown or empty tab falls back to About.
        $tab = (string) $request->query('tab', 'about');
        $tabs = [
            'about',
            'affiliations',
            ...($portfolio['platform'] === null ? [] : ['platform']),
            ...($portfolio['scorecards'] === [] ? [] : ['scorecards']),
            ...array_column($portfolio['sections'], 'key'),
            ...($portfolio['articles'] === [] ? [] : ['news']),
            // Research notes are admin working material (CandidateResource
            // leaves them out for invited viewers), so their tab is too.
            ...($request->user()?->isAdmin() && filled($candidate->notes) ? ['notes'] : []),
        ];

        // Each election has its own page files, so a change to one can't change the other.
        $page = $candidate->election->isProvincial() ? 'Vendor/elections/Provincial/Candidates/Show' : 'Vendor/elections/Candidates/Show';

        return Inertia::render($page, [
            'portfolio' => $portfolio,
            'activeTab' => in_array($tab, $tabs, true) ? $tab : 'about',
            'election' => $candidate->election->toPage(),
            'options' => Options::all(),
        ]);
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        $this->authorize('delete', $candidate);

        $name = $candidate->name;
        $back = $candidate->election->isProvincial() ? 'admin.elections.provincial' : 'admin.elections.index';
        $counts = ['entries' => $candidate->entries()->count(), 'planks' => $candidate->planks()->count()];
        $candidate->delete();
        Tag::pruneOrphans();
        Audit::record('elections.candidate_deleted', "Candidate deleted with everything on file: {$name}", $candidate, [
            'office' => $candidate->office, ...$counts,
        ], 'warning');

        return redirect()
            ->route($back)
            ->with('success', "{$name} and everything on file for them deleted.");
    }

    public function import(Request $request, ImportElectionFromXml $importElectionFromXml): RedirectResponse
    {
        $this->authorize('import', Candidate::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xml', 'max:5120'],
        ]);

        try {
            $result = $importElectionFromXml->handle($validated['file'], $validated['file']->getClientOriginalName());
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.elections.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.elections.index')
            ->with($result['problems'] === [] ? 'success' : 'warning', $importElectionFromXml->summarize($result));
    }

    /**
     * One election's data (?election=<slug>, municipal by default) as the same XML the import reads. Linked with a
     * plain <a href>, not Inertia's <Link>, so the browser downloads it.
     */
    public function export(Request $request, ExportElectionToXml $exportElectionToXml): \Illuminate\Http\Response
    {
        $this->authorize('export', Candidate::class);

        $election = Election::bySlug((string) $request->query('election', Election::MUNICIPAL)) ?? abort(404);
        $filename = ($election->isProvincial() ? 'bc-provincial-election-' : 'salmon-arm-election-').Carbon::today()->toDateString().'.xml';

        return response($exportElectionToXml->handle($election))
            ->header('Content-Type', 'text/xml; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
