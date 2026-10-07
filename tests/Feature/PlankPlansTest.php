<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Plank;

require_once __DIR__.'/fixtures.php';

describe('Plank plans', function () {
    it('imports plans for planks on file, drops unsourced details and reports bad statuses', function () {
        compareFixture();

        $result = app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election><plans>'
            .'<plan candidate="Sam Sample" key="sewage" status="specific"><summary>Finish the plant with borrowing.</summary>'
            .'<detail aspect="how" source_url="https://example.com/sewage">Complete the Water Pollution Control Centre upgrade.</detail>'
            .'<detail aspect="funding" source_url="https://example.com/sewage">Borrow over 20 years.</detail>'
            .'<detail aspect="timeline">No source here.</detail></plan>'
            .'<plan candidate="Jane Example" key="shelter" status="none"><summary>Has stated the goal but not how.</summary></plan>'
            .'<plan candidate="Jane Example" key="shelter" status="maybe"/>'
            .'<plan candidate="Nobody" key="none" status="none"/>'
            .'</plans></election>');

        $sewage = Plank::where('key', 'sewage')->first();
        expect($sewage->plan_status)->toBe('specific')
            ->and($sewage->plan_details)->toHaveCount(2)
            ->and($sewage->plan_details[1]['aspect'])->toBe('funding')
            ->and(Plank::where('key', 'shelter')->value('plan_status'))->toBe('none')
            ->and($result['problems'])->toHaveCount(3);
    });

    it('round-trips plans through the export, and keeps a plan when a later import leaves it out', function () {
        compareFixture();
        $import = app(ImportElectionFromXml::class);
        $import->handleString('<?xml version="1.0" encoding="UTF-8"?><election><plans><plan candidate="Sam Sample" key="sewage" status="partial"><summary>Upgrade first.</summary><detail aspect="how" source_url="https://example.com/sewage">Upgrade the plant.</detail></plan></plans></election>');

        $result = $import->handleString(app(ExportElectionToXml::class)->handle());
        expect($result['problems'])->toBe([])
            ->and(Plank::where('key', 'sewage')->first()->plan_details[0]['text'])->toBe('Upgrade the plant.');

        compareFixture();
        expect(Plank::where('key', 'sewage')->value('plan_status'))->toBe('partial');
    });

    it('sends the plan to the Platform tab, or null when not assessed', function () {
        compareFixture();
        app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election><plans><plan candidate="Sam Sample" key="sewage" status="specific"><summary>S.</summary><detail aspect="timeline" source_url="https://example.com/sewage">By 2028.</detail></plan></plans></election>');
        $sam = Candidate::where('name', 'Sam Sample')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $sam->slug, 'tab' => 'platform']))
            ->assertInertia(fn ($page) => $page
                ->where('portfolio.platform.tiers.0.planks.0.plan.status', 'specific')
                ->where('portfolio.platform.tiers.0.planks.0.plan.details.0.text', 'By 2028.')
                ->where('portfolio.platform.tiers.1.planks.0.plan', null)
                ->where('options.planStatuses.none', 'No plan conveyed to date'));
    });
});
