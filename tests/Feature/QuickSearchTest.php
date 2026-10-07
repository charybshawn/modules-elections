<?php

use App\Models\User;
use Cultpantry\Elections\Models\Candidate;

require_once __DIR__.'/fixtures.php';

describe('Quick search and the scorecard field strip', function () {
    it('serves the quick-search index of candidates, subjects and planks', function () {
        compareFixture();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('admin.elections.search-index'))
            ->assertOk()
            ->assertJsonCount(3, 'candidates')
            ->assertJsonPath('subjects.0.heading', 'Social services')
            ->assertJsonFragment(['title' => 'Finish the sewage plant', 'candidate' => 'Sam Sample', 'candidate_slug' => 'sam-sample', 'tier' => 'top']);
    });

    it('lets invited viewers search but not guests', function () {
        compareFixture();
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())->getJson(route('admin.elections.search-index'))->assertOk();
        auth()->logout();
        $this->getJson(route('admin.elections.search-index'))->assertUnauthorized();
    });

    it("sends every other respondent's answer for the field strip, never the candidate's own", function () {
        compareFixture();
        $jane = Candidate::where('name', 'Jane Example')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $jane->slug, 'tab' => 'scorecards']))
            ->assertInertia(fn ($page) => $page
                ->has('portfolio.scorecards.0.categories.0.items.0.others', 1)
                ->where('portfolio.scorecards.0.categories.0.items.0.others.0.name', 'Sam Sample')
                ->where('portfolio.scorecards.0.categories.0.items.0.others.0.stance', 'opposed'));
    });
});
