<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Actions\BuildUpdateFeed;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\Candidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * The timestamps behind every "new since your last visit" marker (see
 * BuildUpdateFeed). Read-only, so invited viewers get it too; the notes
 * tab's times go to admins only.
 */
class UpdateFeedController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function __invoke(Request $request, BuildUpdateFeed $buildUpdateFeed): JsonResponse
    {
        $this->authorize('viewAny', Candidate::class);

        return response()->json($buildUpdateFeed->handle((bool) $request->user()?->isAdmin()))
            ->header('Cache-Control', 'no-store');
    }
}
