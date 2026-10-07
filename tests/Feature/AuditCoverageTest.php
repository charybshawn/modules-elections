<?php

use Illuminate\Support\Facades\Route;

/*
 * Every action must be auditable. Each route this module registers that
 * changes data writes to the host's Event log through Support\\Audit (or, for invitations, the host's InviteUser). A new write route fails here until it does and
 * is added below.
 */
it('audits every route in this module that changes data', function () {
    $audited = [
        'DELETE admin/elections/articles/{article}',
        'DELETE admin/elections/candidates/{candidate}',
        'DELETE admin/elections/candidates/{candidate}/entries/{entry}',
        'DELETE admin/elections/candidates/{candidate}/planks/{plank}',
        'DELETE admin/elections/events/{event}',
        'DELETE admin/elections/pulse/{snapshot}',
        'POST admin/elections/import',
        'POST admin/elections/invitations',
    ];

    $writeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getActionName(), 'Cultpantry\\Elections\\'))
        ->flatMap(fn ($r) => collect($r->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->map(fn ($m) => "{$m} {$r->uri()}"))
        ->unique()->sort()->values();

    expect($writeRoutes->diff($audited)->values()->all())->toBe([])
        ->and(collect($audited)->diff($writeRoutes)->values()->all())->toBe([]);
});
