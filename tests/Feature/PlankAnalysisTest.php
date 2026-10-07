<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Plank;

require_once __DIR__.'/fixtures.php';

const ANALYSIS_XML = '<?xml version="1.0" encoding="UTF-8"?><election><analyses>'
    .'<analysis candidate="Sam Sample" key="sewage" on="2026-10-03">'
    .'<meaning><point source_url="https://example.com/a">Meets the provincial standard.</point></meaning>'
    .'<works><point source_url="https://example.com/a" source_url-2="https://example.com/b">Costs about $78M.</point><point>Takes years to build.</point></works>'
    .'<fails><point source_url="ftp://bad">Grants may not come.</point></fails>'
    .'<details><point source_url="https://example.com/a">Funding is the real question.</point></details>'
    .'</analysis>'
    .'<analysis candidate="Jane Example" key="shelter"><meaning><point>No sources at all.</point></meaning></analysis>'
    .'<analysis candidate="Nobody" key="x"><meaning><point source_url="https://example.com/a">x</point></meaning></analysis>'
    .'</analyses></election>';

describe('Plank AI analysis', function () {
    it('imports analyses with their sources, and refuses unsourced ones or unknown planks', function () {
        compareFixture();

        $result = app(ImportElectionFromXml::class)->handleString(ANALYSIS_XML);
        $sewage = Plank::where('key', 'sewage')->first();

        expect($sewage->analysis['works'][0]['sources'])->toBe(['https://example.com/a', 'https://example.com/b'])
            ->and($sewage->analysis['works'][1]['sources'])->toBe([])
            ->and($sewage->analysis['fails'][0]['sources'])->toBe([])
            ->and($sewage->analysis['details'][0]['text'])->toBe('Funding is the real question.')
            ->and($sewage->analysis_on->toDateString())->toBe('2026-10-03')
            ->and(Plank::where('key', 'shelter')->value('analysis'))->toBeNull()
            ->and($result['problems'])->toHaveCount(3);
    });

    it('round-trips analyses through the export', function () {
        compareFixture();
        $import = app(ImportElectionFromXml::class);
        $import->handleString(ANALYSIS_XML);
        $before = Plank::where('key', 'sewage')->first()->analysis;

        $result = $import->handleString(app(ExportElectionToXml::class)->handle());

        expect($result['problems'])->toBe([])
            ->and(Plank::where('key', 'sewage')->first()->analysis)->toBe($before);
    });

    it('sends the analysis to the Platform tab', function () {
        compareFixture();
        app(ImportElectionFromXml::class)->handleString(ANALYSIS_XML);
        $sam = Candidate::where('name', 'Sam Sample')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $sam->slug, 'tab' => 'platform']))
            ->assertInertia(fn ($page) => $page
                ->where('portfolio.platform.tiers.0.planks.0.analysis.on', '2026-10-03')
                ->where('portfolio.platform.tiers.0.planks.0.analysis.parts.meaning.0.text', 'Meets the provincial standard.')
                ->where('portfolio.platform.tiers.1.planks.0.analysis', null)
                ->where('options.analysisParts.details', 'The devil is in the details'));
    });

    it('takes an analysis off a plank with remove="true"', function () {
        compareFixture();
        $import = app(ImportElectionFromXml::class);
        $import->handleString(ANALYSIS_XML);

        $import->handleString('<?xml version="1.0" encoding="UTF-8"?><election><analyses><analysis candidate="Sam Sample" key="sewage" remove="true"/></analyses></election>');

        expect(Plank::where('key', 'sewage')->first()->analysis)->toBeNull();
    });

    it('shows the analysis on subject pages too', function () {
        compareFixture();
        app(ImportElectionFromXml::class)->handleString(ANALYSIS_XML);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.tags.show', 'wastewater'))
            ->assertInertia(fn ($page) => $page->where('positions.0.planks.0.analysis.parts.details.0.text', 'Funding is the real question.'));
    });

    it('hides an analysis still in the retired three-part shape', function () {
        compareFixture();
        Plank::where('key', 'sewage')->update(['analysis' => json_encode(['impact' => [['text' => 'old', 'sources' => ['https://example.com/a']]]])]);
        $sam = Candidate::where('name', 'Sam Sample')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $sam->slug, 'tab' => 'platform']))
            ->assertInertia(fn ($page) => $page->where('portfolio.platform.tiers.0.planks.0.analysis', null));
    });
});
