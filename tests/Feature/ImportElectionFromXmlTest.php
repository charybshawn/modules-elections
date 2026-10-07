<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Illuminate\Http\UploadedFile;

function electionXml(string $body): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><election>'.$body.'</election>';
}

const JANE_XML = <<<'XML'
<candidates>
  <candidate>
    <name>Jane Example</name>
    <office>councillor</office>
    <status>nominated</status>
    <is_incumbent>true</is_incumbent>
    <bio>Runs a bakery downtown.</bio>
    <bio_source_url>https://janeexample.ca/about</bio_source_url>
    <entries>
      <entry>
        <kind>plank</kind>
        <topic>housing</topic>
        <summary>Supports more secondary suites.</summary>
        <quote>We need   more suites.</quote>
        <source_url>https://janeexample.ca/platform?a=1&amp;b=2</source_url>
        <source_type>candidate_site</source_type>
        <published_on>2026-09-20</published_on>
      </entry>
      <entry>
        <kind>qa_answer</kind>
        <topic>taxes_budget</topic>
        <question>Asked whether she would freeze taxes.</question>
        <summary>Would not commit to a freeze.</summary>
        <source_url>https://www.saobserver.net/q-and-a</source_url>
        <source_type>news</source_type>
      </entry>
    </entries>
  </candidate>
</candidates>
<articles>
  <article>
    <title>Eleven run for council</title>
    <url>https://www.saobserver.net/eleven</url>
    <outlet>Salmon Arm Observer</outlet>
    <published_on>2026-09-15</published_on>
    <candidates><candidate>Jane Example</candidate></candidates>
  </article>
</articles>
<events>
  <event>
    <title>General voting day</title>
    <kind>general_voting</kind>
    <starts_at>2026-10-17T08:00</starts_at>
    <ends_at>2026-10-17T20:00</ends_at>
  </event>
</events>
XML;

describe('ImportElectionFromXml', function () {
    it('imports candidates, entries, articles and events', function () {
        $result = app(ImportElectionFromXml::class)->handleString(electionXml(JANE_XML));

        expect($result['candidates']['created'])->toBe(1)
            ->and($result['entries']['created'])->toBe(2)
            ->and($result['articles']['created'])->toBe(1)
            ->and($result['events']['created'])->toBe(1)
            ->and($result['problems'])->toBe([]);

        $jane = Candidate::where('name', 'Jane Example')->first();
        expect($jane->slug)->toBe('jane-example')
            ->and($jane->is_incumbent)->toBeTrue()
            ->and($jane->entries)->toHaveCount(2)
            ->and($jane->articles->pluck('title')->all())->toBe(['Eleven run for council'])
            ->and($jane->entries->firstWhere('kind', 'plank')->source_url)->toBe('https://janeexample.ca/platform?a=1&b=2');

        expect(ElectionEvent::first()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-17 08:00');
    });

    it('is a no-op when the same file is imported again', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(electionXml(JANE_XML));
        $result = $import->handleString(electionXml(JANE_XML));

        expect($result['candidates'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1])
            ->and($result['entries'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 2])
            ->and($result['articles'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1])
            ->and($result['events'])->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1])
            ->and(Entry::count())->toBe(2);
    });

    it('matches an entry despite quote whitespace and updates it in place', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(electionXml(JANE_XML));

        $changed = str_replace(
            ['We need   more suites.', '<topic>housing</topic>'],
            ["we need\nmore suites.", '<topic>infrastructure</topic>'],
            JANE_XML,
        );
        $result = $import->handleString(electionXml($changed));

        expect($result['entries']['updated'])->toBe(1)
            ->and(Entry::count())->toBe(2)
            ->and(Entry::where('kind', 'plank')->value('topic'))->toBe('infrastructure');
    });

    it('leaves candidate fields the file omits alone', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(electionXml(JANE_XML));

        $import->handleString(electionXml(
            '<candidates><candidate><name>Jane Example</name><occupation>Baker</occupation></candidate></candidates>'
        ));

        $jane = Candidate::where('name', 'Jane Example')->first();
        expect($jane->occupation)->toBe('Baker')
            ->and($jane->bio)->toBe('Runs a bakery downtown.')
            ->and($jane->status)->toBe('nominated');
    });

    it('skips and reports entries without a source, and unknown candidates on articles', function () {
        $result = app(ImportElectionFromXml::class)->handleString(electionXml(<<<'XML'
            <candidates>
              <candidate>
                <name>Sam Other</name>
                <office>mayor</office>
                <entries>
                  <entry><kind>plank</kind><summary>No source here.</summary><source_type>news</source_type></entry>
                  <entry><kind>plank</kind><summary>Bad scheme.</summary><source_url>javascript:alert(1)</source_url><source_type>news</source_type></entry>
                  <entry><kind>plank</kind><topic>weather</topic><summary>Odd topic.</summary><source_url>https://x.test/a</source_url><source_type>blog</source_type></entry>
                </entries>
              </candidate>
              <candidate><name>No Office</name></candidate>
            </candidates>
            <articles>
              <article><title>T</title><url>https://x.test/t</url><candidates><candidate>Nobody</candidate></candidates></article>
            </articles>
            XML));

        expect($result['candidates']['created'])->toBe(1)
            ->and($result['entries']['created'])->toBe(1)
            ->and(Entry::first()->topic)->toBe('other')
            ->and(Entry::first()->source_type)->toBe('other')
            ->and(Candidate::where('name', 'No Office')->exists())->toBeFalse()
            ->and(Article::first()->candidates)->toHaveCount(0)
            ->and($result['problems'])->toHaveCount(6);
    });

    it('checks background entry topics against the background list', function () {
        $result = app(ImportElectionFromXml::class)->handleString(electionXml(<<<'XML'
            <candidates>
              <candidate>
                <name>Sam Other</name>
                <office>councillor</office>
                <entries>
                  <entry><kind>background</kind><topic>business</topic><summary>Owns a GIS consultancy.</summary><source_url>https://x.test/about</source_url><source_type>linkedin</source_type></entry>
                  <entry><kind>background</kind><topic>housing</topic><summary>Policy topic on a background entry.</summary><source_url>https://x.test/b</source_url><source_type>personal_site</source_type></entry>
                  <entry><kind>plank</kind><topic>business</topic><summary>Background topic on a plank.</summary><source_url>https://x.test/c</source_url><source_type>organization</source_type></entry>
                </entries>
              </candidate>
            </candidates>
            XML));

        expect($result['entries']['created'])->toBe(3)
            ->and(Entry::where('summary', 'Owns a GIS consultancy.')->value('topic'))->toBe('business')
            ->and(Entry::where('summary', 'Owns a GIS consultancy.')->value('source_type'))->toBe('linkedin')
            ->and(Entry::where('summary', 'Policy topic on a background entry.')->value('topic'))->toBe('other')
            ->and(Entry::where('summary', 'Background topic on a plank.')->value('topic'))->toBe('other')
            ->and($result['problems'])->toHaveCount(2);
    });

    it('rejects invalid XML and the wrong root element', function () {
        expect(fn () => app(ImportElectionFromXml::class)->handleString('<election><candidates>'))
            ->toThrow(RuntimeException::class, 'Invalid XML');
        expect(fn () => app(ImportElectionFromXml::class)->handleString('<markets/>'))
            ->toThrow(RuntimeException::class, 'expected an <election> root element');
    });

    it('round-trips through the export as a no-op', function () {
        $import = app(ImportElectionFromXml::class);
        $import->handleString(electionXml(JANE_XML));

        $result = $import->handleString(app(ExportElectionToXml::class)->handle());

        expect($result['candidates']['unchanged'])->toBe(1)
            ->and($result['entries']['unchanged'])->toBe(2)
            ->and($result['articles']['unchanged'])->toBe(1)
            ->and($result['events']['unchanged'])->toBe(1)
            ->and($result['problems'])->toBe([]);
    });

    it('imports through the admin upload and flashes a summary', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->createWithContent('election.xml', electionXml(JANE_XML));

        $this->actingAs($admin)
            ->post(route('admin.elections.import'), ['file' => $file])
            ->assertRedirect(route('admin.elections.index'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'candidates: 1 new'));

        expect(Candidate::count())->toBe(1);
    });
});
