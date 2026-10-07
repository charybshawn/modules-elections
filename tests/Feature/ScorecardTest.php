<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ScorecardAnswer;
use Cultpantry\Elections\Models\ScorecardResponse;
use Cultpantry\Elections\Models\ScorecardTakeaway;

function scorecardXml(string $responses): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<election>
  <scorecards>
    <scorecard>
      <key>v4t</key>
      <title>Vote4Tomorrow</title>
      <publisher>Shuswap Climate Action Society</publisher>
      <url>https://vote4tomorrow.ca/election-2026/salmon-arm/</url>
      <retrieved_on>2026-10-02</retrieved_on>
      <categories>
        <category>
          <key>buildings</key>
          <name>Buildings</name>
          <intro>Healthy homes.</intro>
          <local_context>Salmon Arm is at EL-1.</local_context>
          <sources><source>https://www.salmonarm.ca/524/</source></sources>
          <items>
            <item><key>electrify</key><statement>Support electrification of new buildings.</statement></item>
            <item><key>retrofits</key><statement>Scale up energy retrofits.</statement></item>
          </items>
        </category>
        <category>
          <key>transport</key>
          <name>Transport</name>
          <items>
            <item><key>transit</key><statement>Deliver reliable local transit.</statement></item>
          </items>
        </category>
      </categories>
      <responses>{$responses}</responses>
    </scorecard>
  </scorecards>
</election>
XML;
}

const JANE_RESPONSE = '<response><candidate>Jane Example</candidate><source_url>https://vote4tomorrow.ca/election-2026/salmon-arm/jane/</source_url><responded>true</responded><answers><answer item="electrify">supportive</answer><answer item="retrofits">neutral</answer><answer item="transit">opposed</answer></answers><takeaways><takeaway category="buildings">Points to an early step-code move.</takeaway></takeaways></response>';
const SAM_RESPONSE = '<response><candidate>Sam Sample</candidate><responded>true</responded><answers><answer item="electrify">supportive</answer><answer item="retrofits">supportive</answer><answer item="transit">supportive</answer></answers></response>';
const ALEX_SILENT = '<response><candidate>Alex Silent</candidate><responded>false</responded></response>';

describe('Scorecards', function () {
    beforeEach(function () {
        foreach (['Jane Example', 'Sam Sample', 'Alex Silent'] as $name) {
            Candidate::create(['name' => $name, 'office' => 'councillor', 'status' => 'nominated']);
        }
    });

    it('imports categories, statements, stances and takeaways, and reports bad references', function () {
        $result = app(ImportElectionFromXml::class)->handleString(scorecardXml(
            JANE_RESPONSE.SAM_RESPONSE.ALEX_SILENT
            .'<response><candidate>Nobody</candidate></response>'
            .'<response><candidate>Sam Sample</candidate><answers><answer item="ghost">supportive</answer><answer item="transit">maybe</answer></answers></response>'
        ));

        expect($result['scorecards']['created'])->toBe(1)
            ->and($result['responses']['created'])->toBe(3)
            ->and(ScorecardTakeaway::count())->toBe(1)
            ->and(ScorecardResponse::where('responded', false)->count())->toBe(1)
            ->and($result['problems'])->toHaveCount(3);
    });

    it('is a no-op when re-imported, and replaces answers when they change', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(scorecardXml(JANE_RESPONSE));

        $again = $import->handleString(scorecardXml(JANE_RESPONSE));
        expect($again['scorecards']['unchanged'])->toBe(1)->and($again['responses']['unchanged'])->toBe(1);

        $changed = $import->handleString(scorecardXml(str_replace('<answer item="transit">opposed</answer>', '<answer item="transit">supportive</answer>', JANE_RESPONSE)));
        expect($changed['responses']['updated'])->toBe(1)
            ->and(ScorecardAnswer::count())->toBe(3)
            ->and(ScorecardAnswer::whereHas('item', fn ($q) => $q->where('key', 'transit'))->value('stance'))->toBe('supportive');
    });

    it('round-trips through the export without problems', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(scorecardXml(JANE_RESPONSE.SAM_RESPONSE.ALEX_SILENT));

        $result = $import->handleString(app(ExportElectionToXml::class)->handle());

        expect($result['problems'])->toBe([])
            ->and($result['responses']['unchanged'])->toBe(3)
            ->and(ScorecardAnswer::count())->toBe(6);
    });

    it('shows the scorecard tab with stances, the field split and the takeaway', function () {
        app(ImportElectionFromXml::class)->handleString(scorecardXml(JANE_RESPONSE.SAM_RESPONSE.ALEX_SILENT));
        $jane = Candidate::where('name', 'Jane Example')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $jane->slug, 'tab' => 'scorecards']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeTab', 'scorecards')
                ->where('portfolio.scorecards.0.title', 'Vote4Tomorrow')
                ->where('portfolio.scorecards.0.respondents', 2)
                ->where('portfolio.scorecards.0.totals.supportive', 1)
                ->where('portfolio.scorecards.0.categories.0.name', 'Buildings')
                ->where('portfolio.scorecards.0.categories.0.items.0.stance', 'supportive')
                ->where('portfolio.scorecards.0.categories.0.items.0.field.supportive', 2)
                ->where('portfolio.scorecards.0.categories.0.takeaway', 'Points to an early step-code move.')
                ->where('portfolio.scorecards.0.categories.1.items.0.stance', 'opposed'));
    });

    it('shows a non-responder as not answering, not as opposed', function () {
        app(ImportElectionFromXml::class)->handleString(scorecardXml(JANE_RESPONSE.ALEX_SILENT));
        $alex = Candidate::where('name', 'Alex Silent')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $alex->slug, 'tab' => 'scorecards']))
            ->assertInertia(fn ($page) => $page
                ->where('portfolio.scorecards.0.responded', false)
                ->where('portfolio.scorecards.0.totals.no_response', 3)
                ->where('portfolio.scorecards.0.categories.0.items.0.stance', 'no_response')
                ->where('portfolio.scorecards.0.categories.0.takeaway', null));
    });

    it('leaves the tab out for a candidate with no scorecard response', function () {
        $sam = Candidate::where('name', 'Sam Sample')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $sam->slug, 'tab' => 'scorecards']))
            ->assertInertia(fn ($page) => $page->where('activeTab', 'about')->where('portfolio.scorecards', []));
    });
});
