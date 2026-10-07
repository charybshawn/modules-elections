<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;

const PLANKS_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<election>
  <candidates>
    <candidate>
      <name>Jane Example</name>
      <office>councillor</office>
      <planks>
        <plank>
          <key>secondary-suites</key>
          <title>More secondary suites</title>
          <topic>housing</topic>
          <tier>also</tier>
          <rank>2</rank>
          <rationale>Stated on her site and once in a Q&amp;A.</rationale>
          <entries>
            <entry>
              <kind>statement</kind>
              <summary>Supports more secondary suites.</summary>
              <source_url>https://janeexample.ca/platform</source_url>
              <source_type>candidate_site</source_type>
            </entry>
          </entries>
        </plank>
        <plank>
          <key>downtown-parking</key>
          <title>Fix downtown parking</title>
          <topic>downtown_development</topic>
          <tier>top</tier>
          <rank>1</rank>
          <priority_position>1</priority_position>
          <has_commitment>true</has_commitment>
          <rationale>Her first named priority, repeated in two interviews.</rationale>
          <entries>
            <entry>
              <summary>Will restart the parking commission.</summary>
              <source_url>https://janeexample.ca/platform</source_url>
              <source_type>candidate_site</source_type>
            </entry>
            <entry>
              <summary>Says parking is the top downtown concern.</summary>
              <source_url>https://www.castanet.net/q-and-a</source_url>
              <source_type>news</source_type>
            </entry>
          </entries>
        </plank>
      </planks>
    </candidate>
  </candidates>
</election>
XML;

describe('platform planks', function () {
    it('imports planks with their statements as linked plank entries', function () {
        $result = app(ImportElectionFromXml::class)->handleString(PLANKS_XML);

        expect($result['planks']['created'])->toBe(2)
            ->and($result['entries']['created'])->toBe(3)
            ->and($result['problems'])->toBe([]);

        $jane = Candidate::with('planks.entries')->where('name', 'Jane Example')->first();
        expect($jane->planks->pluck('key')->all())->toBe(['downtown-parking', 'secondary-suites'])
            ->and($jane->planks->first()->entries)->toHaveCount(2)
            ->and($jane->planks->first()->has_commitment)->toBeTrue()
            ->and(Entry::pluck('kind')->unique()->all())->toBe(['plank']);
    });

    it('links a statement already on file instead of duplicating it, and re-imports as a no-op', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(str_replace('<planks>', '<entries><entry><kind>plank</kind><summary>Supports more secondary suites.</summary><source_url>https://janeexample.ca/platform</source_url><source_type>candidate_site</source_type></entry></entries><planks>', PLANKS_XML));

        expect(Entry::count())->toBe(3)
            ->and(Entry::whereNull('plank_id')->count())->toBe(0);

        $again = $import->handleString(PLANKS_XML);
        expect($again['planks'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 2])
            ->and($again['entries']['created'])->toBe(0);
    });

    it('round-trips planks through the export as a no-op', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(PLANKS_XML);

        $result = $import->handleString(app(ExportElectionToXml::class)->handle());

        expect($result['planks'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 2])
            ->and($result['entries']['created'])->toBe(0)
            ->and($result['problems'])->toBe([]);
    });

    it('reports a plank with an unknown tier or no rationale', function () {
        $result = app(ImportElectionFromXml::class)->handleString(str_replace(
            ['<tier>also</tier>', '<rationale>Stated on her site and once in a Q&amp;A.</rationale>'],
            ['<tier>urgent</tier>', ''],
            PLANKS_XML,
        ));

        expect(Plank::where('key', 'secondary-suites')->value('tier'))->toBe('mentioned')
            ->and($result['problems'])->toHaveCount(2);
    });

    it('shows the Platform tab in tier order and lets an admin delete a plank with its statements', function () {
        app(ImportElectionFromXml::class)->handleString(PLANKS_XML);
        $jane = Candidate::where('name', 'Jane Example')->first();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.elections.candidates.show', ['candidate' => $jane, 'tab' => 'platform']))
            ->assertInertia(fn ($page) => $page
                ->where('activeTab', 'platform')
                ->where('portfolio.platform.tiers.0.tier', 'top')
                ->where('portfolio.platform.tiers.0.planks.0.key', 'downtown-parking')
                ->where('portfolio.platform.tiers.0.planks.0.source_count', 2)
                ->where('portfolio.platform.tiers.1.tier', 'also')
                ->where('portfolio.platform.limited_sources', false));

        $plank = $jane->planks()->where('key', 'downtown-parking')->first();
        $this->actingAs($admin)
            ->delete(route('admin.elections.planks.destroy', [$jane, $plank]))
            ->assertRedirect();

        expect(Plank::count())->toBe(1)->and(Entry::count())->toBe(1);
    });

    it('records rank history when a plank appears and when its place changes', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(PLANKS_XML);
        $import->handleString(PLANKS_XML);

        $suites = Plank::where('key', 'secondary-suites')->first();
        expect($suites->rankHistory()->count())->toBe(1);

        $import->handleString(str_replace(['<tier>also</tier>', '<rank>2</rank>'], ['<tier>top</tier>', '<rank>1</rank>'], PLANKS_XML));

        expect($suites->rankHistory()->pluck('rank')->all())->toBe([2, 1])
            ->and($suites->rankHistory()->pluck('tier')->all())->toBe(['also', 'top']);

        $jane = Candidate::where('name', 'Jane Example')->first();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', ['candidate' => $jane, 'tab' => 'platform']))
            ->assertInertia(fn ($page) => $page
                // Both now rank 1 in the top tier; ties fall back to creation order.
                ->where('portfolio.platform.tiers.0.planks.0.key', 'secondary-suites')
                ->has('portfolio.platform.tiers.0.planks.0.history', 2)
                ->has('portfolio.platform.tiers.0.planks.1.history', 1));
    });

    it("404s deleting a plank through another candidate's URL", function () {
        app(ImportElectionFromXml::class)->handleString(PLANKS_XML);
        $other = Candidate::create(['name' => 'Sam Other', 'office' => 'mayor', 'status' => 'declared']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('admin.elections.planks.destroy', [$other, Plank::first()]))
            ->assertNotFound();
    });
});
