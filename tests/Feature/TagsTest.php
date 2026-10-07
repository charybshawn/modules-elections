<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\Tag;
use Illuminate\Support\Facades\DB;

const TAG_VOCAB = '<vocabulary>'
    .'<tag><slug>homelessness</slug><topic>social_services</topic><name>Homelessness</name><description>Shelters and services.</description></tag>'
    .'<tag><slug>public-space-safety</slug><topic>public_safety</topic><name>Safety in public spaces</name></tag>'
    .'<tag><slug>wastewater</slug><topic>infrastructure</topic><name>Wastewater treatment</name></tag>'
    .'</vocabulary>';

function taggedPlank(string $candidate, string $key, string $title, string $tags): string
{
    return "<candidate><name>{$candidate}</name><office>councillor</office><status>nominated</status><planks>"
        ."<plank><key>{$key}</key><title>{$title}</title><topic>social_services</topic><tier>top</tier><rank>1</rank><rationale>r</rationale>"
        .$tags
        ."<entries><entry><kind>plank</kind><topic>social_services</topic><summary>{$title} statement.</summary><source_url>https://example.com/{$key}</source_url><source_type>candidate_site</source_type></entry></entries>"
        .'</plank></planks></candidate>';
}

function tagsXml(string $body): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><election>'.$body.'</election>';
}

function seedTagged(): void
{
    app(ImportElectionFromXml::class)->handleString(tagsXml(
        TAG_VOCAB
        .'<candidates>'
        .taggedPlank('Jane Example', 'shelter', 'More shelter beds', '<tags><tag>homelessness</tag><tag>public-space-safety</tag></tags>')
        .taggedPlank('Sam Sample', 'sewage', 'Finish the sewage plant', '<tags><tag>wastewater</tag></tags>')
        .'<candidate><name>Alex Quiet</name><office>councillor</office><status>nominated</status></candidate>'
        .'</candidates>'
        .'<articles><article><title>Shelter debate</title><url>https://news.example/shelter</url><tags><tag>homelessness</tag></tags><candidates><candidate>Jane Example</candidate></candidates></article></articles>'
    ));
}

describe('Tags', function () {
    it('imports the vocabulary and inline tags, and reports unknown slugs without creating them', function () {
        $result = app(ImportElectionFromXml::class)->handleString(tagsXml(
            TAG_VOCAB.'<candidates>'.taggedPlank('Jane Example', 'shelter', 'More shelter beds', '<tags><tag>homelessness</tag><tag>made-up</tag></tags>').'</candidates>'
        ));

        expect($result['tags']['created'])->toBe(3)
            ->and(Plank::first()->tags->pluck('slug')->all())->toBe(['homelessness'])
            ->and(Tag::where('slug', 'made-up')->exists())->toBeFalse()
            ->and(collect($result['problems'])->filter(fn ($p) => str_contains($p, 'made-up')))->toHaveCount(1);
    });

    it('re-tags records already on file through <tagging>, and an empty <tags/> clears them', function () {
        seedTagged();
        $import = app(ImportElectionFromXml::class);

        $result = $import->handleString(tagsXml(
            '<tagging>'
            .'<plank candidate="Sam Sample" key="sewage"><tags><tag>wastewater</tag><tag>public-space-safety</tag></tags></plank>'
            .'<article url="https://news.example/shelter"><tags/></article>'
            .'<plank candidate="Nobody" key="none"><tags><tag>wastewater</tag></tags></plank>'
            .'</tagging>'
        ));

        expect(Plank::where('key', 'sewage')->first()->tags->pluck('slug')->sort()->values()->all())->toBe(['public-space-safety', 'wastewater'])
            ->and(Article::first()->tags)->toHaveCount(0)
            ->and($result['tagged']['updated'])->toBe(2)
            ->and($result['problems'])->toHaveCount(1);

        $again = $import->handleString(tagsXml('<tagging><plank candidate="Sam Sample" key="sewage"><tags><tag>wastewater</tag><tag>public-space-safety</tag></tags></plank></tagging>'));
        expect($again['tagged']['unchanged'])->toBe(1);
    });

    it('round-trips tags through the export without problems', function () {
        seedTagged();
        $before = DB::table('elections_taggables')->count();

        $result = app(ImportElectionFromXml::class)->handleString(app(ExportElectionToXml::class)->handle());

        expect($result['problems'])->toBe([])
            ->and($result['tags']['unchanged'])->toBe(3)
            ->and(DB::table('elections_taggables')->count())->toBe($before);
    });

    it('shows a tag page with where each candidate stands, who has nothing on file, news and related tags', function () {
        seedTagged();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.tags.show', 'homelessness'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Tags/Show')
                ->where('tag.heading', 'Social services')
                ->has('positions', 1)
                ->where('positions.0.name', 'Jane Example')
                ->where('positions.0.planks.0.title', 'More shelter beds')
                ->where('nothingOnFile', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === ['Alex Quiet', 'Sam Sample'])
                ->has('articles', 1)
                ->where('related.0.slug', 'public-space-safety'));
    });

    it('lists the directory by heading with candidate counts', function () {
        seedTagged();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.tags.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Tags/Index')
                ->where('headings.0.title', 'Infrastructure')
                ->where('mostCovered', fn ($rows) => collect($rows)->firstWhere('slug', 'homelessness')['candidates'] === 1));
    });

    it('matches Community Pulse issues to planks by shared tags', function () {
        seedTagged();
        app(ImportElectionFromXml::class)->handleString(tagsXml(
            '<pulse><taken_on>2026-10-03</taken_on><issues>'
            .'<issue><key>camping</key><title>Camping in parks</title><topic>public_safety</topic><voices>9</voices><tags><tag>public-space-safety</tag></tags></issue>'
            .'</issues></pulse>'
        ));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.pulse.index'))
            ->assertInertia(fn ($page) => $page
                ->where('snapshot.issues.0.coverage_by', 'tags')
                ->where('snapshot.issues.0.coverage.top.0.name', 'Jane Example')
                ->where('snapshot.issues.0.tags.0.slug', 'public-space-safety'));
    });

    it('drops tag links when the tagged record is deleted', function () {
        seedTagged();
        $jane = Candidate::where('name', 'Jane Example')->first();
        $plank = $jane->planks()->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.elections.planks.destroy', [$jane->slug, $plank->id]));

        expect(DB::table('elections_taggables')->where('taggable_type', 'elections_plank')->where('taggable_id', $plank->id)->count())->toBe(0);
    });

    it('heats the dashboard map by how often and how recently a subject comes up', function () {
        app(ImportElectionFromXml::class)->handleString(tagsXml(
            TAG_VOCAB.'<candidates>'
            .str_replace('</source_type>', '</source_type><published_on>'.now()->subDays(2)->toDateString().'</published_on>', taggedPlank('Jane Example', 'shelter', 'More shelter beds', '<tags><tag>homelessness</tag></tags>'))
            .str_replace('</source_type>', '</source_type><published_on>'.now()->subDays(120)->toDateString().'</published_on>', taggedPlank('Sam Sample', 'sewage', 'Finish the sewage plant', '<tags><tag>wastewater</tag></tags>'))
            .'</candidates>'
        ));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.index'))
            ->assertInertia(function ($page) {
                $tags = collect($page->toArray()['props']['heat']['headings'])->flatMap(fn ($h) => $h['tags'])->keyBy('slug');
                expect($tags['homelessness']['level'])->toBe(4)
                    ->and($tags['homelessness']['recent'])->toBe(1)
                    ->and($tags['wastewater']['heat'])->toBeLessThan(0.05)
                    ->and($tags['public-space-safety']['mentions'])->toBe(0);
            });
    });

    it('sends who-campaigns-on-what to the dashboard and the subject matrix', function () {
        seedTagged();
        $jane = Candidate::where('name', 'Jane Example')->first();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.elections.index'))
            ->assertInertia(fn ($page) => $page
                ->where("coverage.cells.{$jane->id}.homelessness.tier", 'top')
                ->where("coverage.cells.{$jane->id}.homelessness.planks.0", 'More shelter beds')
                ->where('coverage.tags.0.candidates', 1));

        $this->actingAs($admin)->get(route('admin.elections.tags.index'))
            ->assertInertia(fn ($page) => $page
                ->has('candidates', 3)
                ->where("coverage.cells.{$jane->id}.public-space-safety.tier", 'top'));
    });

    it('lets invited viewers browse subjects', function () {
        seedTagged();
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        $this->actingAs($viewer->fresh())->get(route('admin.elections.tags.index'))->assertOk();
        $this->actingAs($viewer->fresh())->get(route('admin.elections.tags.show', 'homelessness'))->assertOk();
    });
});
