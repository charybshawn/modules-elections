<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\PulseIssue;
use Cultpantry\Elections\Models\PulseSnapshot;

function pulseXml(string $date, string $issues, string $mentions = ''): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<election>
  <pulse>
    <taken_on>{$date}</taken_on>
    <threads_read>4</threads_read>
    <commenters>60</commenters>
    <sources>the Rant and Rave group</sources>
    <conclusions><conclusion issue="homelessness">Homelessness draws the most people.</conclusion></conclusions>
    <issues>{$issues}</issues>
    <mentions>{$mentions}</mentions>
  </pulse>
</election>
XML;
}

const PULSE_HOMELESS = '<issue><key>homelessness</key><title>Homelessness</title><topic>social_services</topic><voices>30</voices><support_pct>20</support_pct><oppose_pct>50</oppose_pct><mixed_pct>30</mixed_pct><heat>high</heat><summary>Split between services and enforcement.</summary><wants><want>More shelter beds</want></wants><questions><question>What can the city actually do?</question></questions></issue>';
const PULSE_POOL = '<issue><key>pool</key><title>A new pool</title><topic>recreation_parks</topic><voices>12</voices><heat>medium</heat></issue>';

describe('Community Pulse', function () {
    beforeEach(function () {
        $this->jane = Candidate::create(['name' => 'Jane Example', 'office' => 'councillor', 'status' => 'nominated']);
        $plank = $this->jane->planks()->create(['key' => 'shelter', 'title' => 'More shelter capacity', 'topic' => 'social_services', 'tier' => 'top', 'rank' => 1, 'rationale' => 'r']);
        $entry = $this->jane->entries()->make(['kind' => 'plank', 'topic' => 'social_services', 'summary' => 'Wants more shelter beds.', 'source_url' => 'https://example.com/platform', 'source_type' => 'candidate_site']);
        $entry->plank_id = $plank->id;
        $entry->save();
    });

    it('imports a snapshot with issues and candidate mentions', function () {
        $result = app(ImportElectionFromXml::class)->handleString(pulseXml('2026-10-03', PULSE_HOMELESS.PULSE_POOL,
            '<mention><candidate>Jane Example</candidate><mentions>5</mentions><commenters>4</commenters></mention><mention><candidate>Nobody</candidate><mentions>1</mentions></mention>'));

        $snapshot = PulseSnapshot::first();
        expect($result['pulse']['created'])->toBe(1)
            ->and($snapshot->issues->pluck('key')->all())->toBe(['homelessness', 'pool'])
            ->and($snapshot->issues->first()->wants)->toBe(['More shelter beds'])
            ->and($snapshot->mentions->first()->mentions)->toBe(5)
            ->and($result['problems'])->toHaveCount(1);
    });

    it('replaces a snapshot wholesale when the same date is re-imported', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(pulseXml('2026-10-03', PULSE_HOMELESS.PULSE_POOL));
        $result = $import->handleString(pulseXml('2026-10-03', PULSE_HOMELESS));

        expect($result['pulse']['updated'])->toBe(1)
            ->and(PulseSnapshot::count())->toBe(1)
            ->and(PulseIssue::pluck('key')->all())->toBe(['homelessness']);
    });

    it('round-trips through the export without problems', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(pulseXml('2026-10-03', PULSE_HOMELESS.PULSE_POOL, '<mention><candidate>Jane Example</candidate><mentions>5</mentions><commenters>4</commenters></mention>'));

        $result = $import->handleString(app(ExportElectionToXml::class)->handle());

        expect($result['problems'])->toBe([])
            ->and(PulseIssue::count())->toBe(2)
            ->and(PulseSnapshot::first()->conclusions[0]['issue'])->toBe('homelessness');
    });

    it('shows the latest snapshot with change since the last one and plank coverage', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(pulseXml('2026-09-28', str_replace('<voices>30</voices>', '<voices>20</voices>', PULSE_HOMELESS)));
        $import->handleString(pulseXml('2026-10-03', PULSE_HOMELESS.PULSE_POOL));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.pulse.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Pulse')
                ->where('snapshot.taken_on', '2026-10-03')
                ->where('snapshot.previous_taken_on', '2026-09-28')
                ->where('snapshot.issues.0.voices_change', 10)
                ->where('snapshot.issues.1.voices_change', 'new')
                ->where('snapshot.issues.0.coverage.top.0.slug', $this->jane->slug)
                ->has('snapshots', 2));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.pulse.index', ['date' => '2026-09-28']))
            ->assertInertia(fn ($page) => $page->where('snapshot.taken_on', '2026-09-28')->where('snapshot.previous_taken_on', null));
    });

    it('lets invited viewers read it but only admins delete a snapshot', function () {
        app(ImportElectionFromXml::class)->handleString(pulseXml('2026-10-03', PULSE_HOMELESS));
        $snapshot = PulseSnapshot::first();
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())->get(route('admin.elections.pulse.index'))->assertOk();
        $this->actingAs($viewer->fresh())->delete(route('admin.elections.pulse.destroy', $snapshot))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.elections.pulse.destroy', $snapshot))
            ->assertRedirect(route('admin.elections.pulse.index'));
        expect(PulseSnapshot::count())->toBe(0)->and(PulseIssue::count())->toBe(0);
    });

    it('shows an empty state before any snapshot exists', function () {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.pulse.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('snapshot', null)->has('snapshots', 0));
    });
});
