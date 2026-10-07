<?php

use App\Models\User;
use Cultpantry\Elections\Support\CityPlans;

describe('City plans info sheets', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
    });

    it('lists the Corporate Strategic Plan and the Official Community Plan', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.elections.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Plans/Index', false)
                ->has('plans', 2)
                ->where('plans.0.slug', 'strategic-plan')
                ->where('plans.1.slug', 'ocp')
                ->has('plans.0.stats'));
    });

    it('shows each sheet with its source document and page-cited sections', function (string $slug) {
        $this->actingAs($this->admin)
            ->get(route('admin.elections.plans.show', $slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Plans/Show', false)
                ->where('plan.slug', $slug)
                ->where('plan.source_url', fn ($url) => str_starts_with($url, 'https://salmonarm.ca/DocumentCenter/'))
                ->has('plan.sections'));
    })->with(['strategic-plan', 'ocp']);

    it('cites a page for every summary paragraph, number and point', function () {
        foreach (CityPlans::all() as $plan) {
            $cited = [...$plan['summary'], ...$plan['stats']];
            foreach ($plan['sections'] as $section) {
                $cited = [...$cited, ...($section['points'] ?? []), ...($section['groups'] ?? [])];
                if (isset($section['intro'])) {
                    $cited[] = $section['intro'];
                }
            }
            foreach ($cited as $item) {
                expect($item['pages'] ?? [])->not->toBeEmpty();
            }
        }
    });

    it('404s an unknown plan', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.elections.plans.show', 'no-such-plan'))
            ->assertNotFound();
    });

    it('lets invited Elections viewers read the sheets', function () {
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())
            ->get(route('admin.elections.plans.show', 'ocp'))
            ->assertOk();
    });
});
