<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Support\Audit;
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
        Audit::record('elections.event_deleted', "Election event deleted: {$event->title}", $event, [
            'starts_at' => $event->starts_at?->toIso8601String(), 'kind' => $event->kind,
        ], 'warning');

        return redirect()->route('admin.elections.index')->with('success', 'Event deleted.');
    }
}
