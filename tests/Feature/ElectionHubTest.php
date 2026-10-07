<?php

use App\Models\User;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;

describe('elections landing page', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
    });

    it('shows the two elections, with the Salmon Arm candidate count and voting day', function () {
        Candidate::create(['name' => 'Jane Example', 'office' => 'councillor', 'status' => 'nominated']);
        Candidate::create(['name' => 'Old Hopeful', 'office' => 'councillor', 'status' => 'withdrawn']);
        ElectionEvent::create(['title' => 'General voting day', 'kind' => 'general_voting', 'starts_at' => '2026-10-17 08:00:00']);

        $this->actingAs($this->admin)
            ->get('/admin/elections')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Home', false)
                ->where('salmonArm.candidates', 1)
                ->where('salmonArm.votingDay', fn ($v) => str_starts_with($v, '2026-10-17')));
    });

    it('serves the Salmon Arm dashboard at its own address', function () {
        expect(route('admin.elections.index', [], false))->toBe('/admin/elections/salmon-arm');

        $this->actingAs($this->admin)
            ->get('/admin/elections/salmon-arm')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/elections/Index', false));
    });

    it('shows the provincial placeholder page', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.elections.provincial'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/elections/Provincial', false));
    });

    it('lets invited Elections viewers open both pages, and keeps customers out', function () {
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())->get('/admin/elections')->assertOk();
        $this->actingAs($viewer->fresh())->get(route('admin.elections.provincial'))->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin/elections')
            ->assertForbidden();
    });
});
