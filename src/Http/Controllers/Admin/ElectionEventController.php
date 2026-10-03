<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\ElectionEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Events only arrive by XML import; this removes one from the dashboard.
 */
class ElectionEventController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public function destroy(ElectionEvent $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return redirect()->route('admin.elections.index')->with('success', 'Event deleted.');
    }
}
