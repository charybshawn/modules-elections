<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Support\CityFinances;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Elections -> City finances: the City's money in, money out, debt and the
 * big capital bills ahead (content and sources in Support\CityFinances).
 * Read-only, so invited viewers see it too.
 */
class CityFinanceController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function show(): Response
    {
        $this->authorize('viewAny', Candidate::class);

        return Inertia::render('Vendor/elections/Finances/Show', ['finances' => CityFinances::sheet()]);
    }
}
