<?php

namespace Cultpantry\Elections\Http\Controllers\Admin\Concerns;

use App\Actions\GetSiteSetting;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Layer 2 of the three (route middleware is 1, policies are 3), plus the
 * Settings -> Modules enable toggle, applied to every action of every
 * controller in this module.
 *
 * canAccessAdminPanel() rather than isAdmin(): invited viewers are panel
 * users granted the Elections section and must be able to open it.
 * AdminMiddleware already confines them to this section and to safe
 * methods, and the policies keep every write admin-only.
 */
trait ElectionsAdminMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->canAccessAdminPanel(), 403, 'Admin access required.');

                return $next($request);
            }),
            new Middleware(function ($request, $next) {
                abort_unless(app(GetSiteSetting::class)->handle('modules.cultpantry/elections.enabled', true), 404);

                return $next($request);
            }),
        ];
    }
}
