<?php

use App\Models\User;
use Cultpantry\Elections\Support\PlanProgress;

describe('City plans progress scoresheet', function () {
    it('accounts for every priority project in the Corporate Strategic Plan', function () {
        $sheet = PlanProgress::strategicPlan();
        $names = collect($sheet['groups'])->flatMap(fn ($g) => collect($g['projects'])->pluck('name'));

        expect($names)->toHaveCount(22)
            ->and($sheet['tally']['total'])->toBe(22)
            ->and($sheet['tally']['complete'] + $sheet['tally']['underway'] + $sheet['tally']['planning'] + $sheet['tally']['paused'] + $sheet['tally']['unknown'])->toBe(22);
    });

    it('weights every status for the completion gauge', function () {
        $weights = collect(PlanProgress::strategicPlan()['scale'])->pluck('weight', 'key');

        expect($weights->keys()->all())->toBe(['complete', 'underway', 'planning', 'paused', 'unknown'])
            ->and($weights['complete'])->toBe(100)
            ->and($weights['unknown'])->toBe(0);
    });

    it('cites a source for every project that reports progress', function () {
        foreach (PlanProgress::strategicPlan()['groups'] as $group) {
            foreach ($group['projects'] as $project) {
                if ($project['status'] !== 'unknown') {
                    expect($project['sources'])->not->toBeEmpty("{$project['name']} has no source");
                }
            }
        }
        foreach (PlanProgress::strategicPlan()['capex']['items'] as $item) {
            expect($item['sources'])->not->toBeEmpty();
        }
    });

    it('serves the scoresheet to Elections viewers and links it from the plan sheet', function () {
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())
            ->get('/admin/elections/plans/strategic-plan-progress')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/elections/Plans/Progress', false)->where('sheet.tally.total', 22));

        $this->actingAs($viewer->fresh())
            ->get('/admin/elections/plans/strategic-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('progressSlug', 'strategic-plan-progress'));

        $this->actingAs($viewer->fresh())
            ->get('/admin/elections/plans/ocp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('progressSlug', null));

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin/elections/plans/strategic-plan-progress')
            ->assertForbidden();
    });

    it('includes the scoresheet in the update feed', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/admin/elections/updates')
            ->assertOk()
            ->assertJsonPath('sections.plans.strategic-plan-progress', fn ($at) => is_int($at))
            ->assertJsonPath('sections.plans.city-finances', fn ($at) => is_int($at));
    });

    it('lists the plans, the scoresheet and the finances page together on one index', function () {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/elections/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Plans/Index', false)
                ->has('plans', 2)
                ->has('progress', 1)
                ->where('finances.slug', 'city-finances'));
    });

    it('404s for an unknown scoresheet', function () {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/elections/plans/nope-progress')
            ->assertNotFound();
    });
});
