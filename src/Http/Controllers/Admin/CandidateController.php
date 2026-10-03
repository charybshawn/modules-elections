<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildCandidatePortfolio;
use Cultpantry\Elections\Actions\BuildElectionDashboard;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
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
            ...$buildElectionDashboard->handle(),
            'options' => Options::all(),
        ]);
    }

    public function show(Candidate $candidate, BuildCandidatePortfolio $buildCandidatePortfolio): Response
    {
        $this->authorize('view', $candidate);

        return Inertia::render('Vendor/elections/Candidates/Show', [
            'portfolio' => $buildCandidatePortfolio->handle($candidate),
            'options' => Options::all(),
        ]);
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        $this->authorize('delete', $candidate);

        $name = $candidate->name;
        $candidate->delete();

        return redirect()
            ->route('admin.elections.index')
            ->with('success', "{$name} and everything on file for them deleted.");
    }

    public function import(Request $request, ImportElectionFromXml $importElectionFromXml): RedirectResponse
    {
        $this->authorize('import', Candidate::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xml', 'max:5120'],
        ]);

        try {
            $result = $importElectionFromXml->handle($validated['file']);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.elections.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.elections.index')
            ->with($result['problems'] === [] ? 'success' : 'warning', $importElectionFromXml->summarize($result));
    }

    /**
     * The whole dataset as the same XML the import reads. Linked with a
     * plain <a href>, not Inertia's <Link>, so the browser downloads it.
     */
    public function export(ExportElectionToXml $exportElectionToXml): \Illuminate\Http\Response
    {
        $this->authorize('export', Candidate::class);

        $filename = 'salmon-arm-election-'.Carbon::today()->toDateString().'.xml';

        return response($exportElectionToXml->handle())
            ->header('Content-Type', 'text/xml; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
