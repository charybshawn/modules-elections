<?php

use App\Models\User;
use Cultpantry\Elections\Models\Candidate;

require_once __DIR__.'/fixtures.php';

describe('Compare', function () {
    it('lines candidates up in the picked order, with shared subjects first and scorecard differences flagged', function () {
        compareFixture();
        [$jane, $sam, $alex] = ['Jane Example', 'Sam Sample', 'Alex Quiet'];
        $ids = Candidate::pluck('id', 'name');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.compare', ['c' => ['sam-sample', 'jane-example', 'alex-quiet']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Compare')
                ->where('people.0.name', $sam)
                ->where('people.0.planks.0.title', 'Finish the sewage plant')
                ->where('people.1.name', $jane)
                ->where('subjects.0.slug', 'homelessness')
                ->where('subjects.0.shared', 2)
                ->where("subjects.0.by.{$ids[$jane]}.tier", 'top')
                ->where('subjects.1.slug', 'wastewater')
                ->where('scorecards.0.categories.0.items.0.differs', true)
                ->where('scorecards.0.categories.0.items.1.differs', false)
                ->where("scorecards.0.categories.0.items.0.stances.{$ids[$alex]}", 'no_response')
                ->where("scorecards.0.responded.{$ids[$alex]}", false)
                ->has('everyone', 3));
    });

    it('ignores unknown slugs and caps the comparison at three', function () {
        compareFixture();
        Candidate::create(['name' => 'Fourth Person', 'office' => 'councillor', 'status' => 'nominated']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.compare', ['c' => ['nobody', 'jane-example', 'sam-sample', 'alex-quiet', 'fourth-person']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('people', 2)->where('people.0.name', 'Jane Example'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.compare'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('people', 0)->where('max', 3));
    });

    it('lets invited viewers compare', function () {
        compareFixture();
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())
            ->get(route('admin.elections.compare', ['c' => ['jane-example', 'sam-sample']]))
            ->assertOk();
    });
});
