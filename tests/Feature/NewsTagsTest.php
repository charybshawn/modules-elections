<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Tag;

/*
 * News-type tags (personal, legislative, opinion, background) label what kind
 * of story an article is. A story has one, so it sits in one column, and they
 * never appear as subjects: not in Browse by subject, the heat map, search or the "new" badge.
 */

function newsTagsImport(string $inner): array
{
    return app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election>'.$inner.'</election>');
}

describe('news tags', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $result = newsTagsImport(
            '<vocabulary>'
            .'<tag><slug>housing</slug><topic>housing</topic><name>Housing</name></tag>'
            .'<tag><slug>news-personal</slug><kind>news</kind><topic>other</topic><name>Personal</name></tag>'
            .'<tag><slug>news-legislative</slug><kind>news</kind><topic>other</topic><name>Legislative</name></tag>'
            .'</vocabulary>'
            .'<candidates><candidate><name>Jane Example</name><office>councillor</office><status>nominated</status></candidate></candidates>'
            .'<articles><article><title>Councillor posts, then votes on rezoning</title><url>https://news.example/both</url>'
            .'<candidates><candidate>Jane Example</candidate></candidates>'
            .'<tags><tag>housing</tag><tag>news-personal</tag></tags></article></articles>'
        );
        expect($result['problems'])->toBe([]);
    });

    it('gives a story one news tag alongside its subjects', function () {
        $article = Article::where('url', 'https://news.example/both')->first();

        expect($article->tags->pluck('slug')->sort()->values()->all())->toBe(['housing', 'news-personal'])
            ->and(Tag::where('slug', 'news-personal')->value('kind'))->toBe('news')
            ->and(Tag::where('slug', 'housing')->value('kind'))->toBe('subject');
    });

    it('sends the candidate page both kinds, labelled', function () {
        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', ['candidate' => Candidate::first()->slug, 'tab' => 'news']))
            ->assertInertia(fn ($page) => $page->where('portfolio.articles.0.tags', fn ($tags) => collect($tags)->pluck('kind', 'slug')->all() === [
                'housing' => 'subject', 'news-personal' => 'news',
            ]));
    });

    it('keeps only the first news tag when a story is given several, and says so', function () {
        $result = newsTagsImport('<articles><article><title>Councillor posts, then votes on rezoning</title><url>https://news.example/both</url>'
            .'<tags><tag>housing</tag><tag>news-legislative</tag><tag>news-personal</tag></tags></article></articles>');

        expect(collect($result['problems'])->join(' '))->toContain('more than one news-type tag', 'kept news-legislative')
            ->and(Article::where('url', 'https://news.example/both')->first()->tags->pluck('slug')->sort()->values()->all())
            ->toBe(['housing', 'news-legislative']);
    });

    it('keeps news tags out of every subject view', function () {
        $admin = $this->actingAs($this->admin);

        $admin->get(route('admin.elections.tags.index'))->assertInertia(function ($page) {
            $slugs = collect($page->toArray()['props']['headings'])->pluck('tags')->flatten(1)->pluck('slug')->all();
            expect($slugs)->toBe(['housing']);
        });

        $search = $admin->getJson(route('admin.elections.search-index'))->json();
        expect(collect($search['subjects'])->pluck('slug')->all())->toBe(['housing']);

        $admin->get(route('admin.elections.tags.show', 'news-personal'))->assertNotFound();
        $admin->get(route('admin.elections.tags.show', 'housing'))->assertOk();
    });

    it('does not light up Browse by subject for a news tag', function () {
        $this->travelTo(now()->addDay());
        $after = now()->getTimestamp();
        newsTagsImport('<tagging><article url="https://news.example/both"><tags><tag>housing</tag><tag>news-personal</tag></tags></article></tagging>');

        $feed = $this->actingAs($this->admin)->getJson(route('admin.elections.updates'))->json();
        expect(collect($feed['sections']['subjects'])->filter(fn ($t) => $t >= $after)->all())->toBe([]);
    });

    it('rejects an unknown tag kind and exports kind with the vocabulary', function () {
        $result = newsTagsImport('<vocabulary><tag><slug>odd</slug><kind>sideways</kind><name>Odd</name></tag></vocabulary>');
        expect(collect($result['problems'])->join(' '))->toContain('unknown kind')
            ->and(Tag::where('slug', 'odd')->value('kind'))->toBe('subject');

        expect(app(ExportElectionToXml::class)->handle())->toContain('<kind>news</kind>', '<slug>news-personal</slug>');
    });
});
