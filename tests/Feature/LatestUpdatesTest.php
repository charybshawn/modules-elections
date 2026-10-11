<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;

require_once __DIR__.'/fixtures.php';

describe('Latest updates', function () {
    it('groups each candidate\'s additions per day and lists news stories, newest first', function () {
        $this->travelTo(now()->subDays(3));
        compareFixture();
        $this->travelBack();
        app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election>'
            .'<candidates><candidate><name>Jane Example</name><entries>'
            .'<entry><kind>statement</kind><topic>housing</topic><summary>One.</summary><source_url>https://example.com/1</source_url><source_type>news</source_type></entry>'
            .'<entry><kind>statement</kind><topic>housing</topic><summary>Two.</summary><source_url>https://example.com/2</source_url><source_type>news</source_type></entry>'
            .'</entries></candidate></candidates>'
            .'<articles><article><title>Fresh story</title><url>https://news.example/fresh</url><outlet>Observer</outlet><candidates><candidate>Jane Example</candidate></candidates></article></articles>'
            .'</election>');
        $jane = Candidate::where('name', 'Jane Example')->first();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.index'))
            ->assertInertia(function ($page) use ($jane) {
                $updates = collect($page->toArray()['props']['latestUpdates']);
                $today = $updates->first(fn ($u) => $u['type'] === 'candidate' && $u['slug'] === $jane->slug && $u['total'] === 2);
                $older = $updates->first(fn ($u) => $u['type'] === 'candidate' && $u['slug'] === $jane->slug && $u['total'] !== 2);

                $entryIds = $jane->entries()->where('summary', 'like', 'One.')->orWhere('summary', 'Two.')->orderBy('id')->pluck('id')->all();

                expect($today['counts'])->toBe(['2 statements'])
                    ->and(collect($today['items'])->pluck('focus')->all())->toBe(array_map(fn ($id) => 'entry-'.$id, $entryIds))
                    ->and($today['items'][0])->toMatchArray(['label' => 'One.', 'kind' => 'statement', 'tab' => 'words'])
                    ->and($today['more_items'])->toBe(0)
                    ->and(collect($older['items'])->pluck('focus')->all())->toContain('plank-'.$jane->planks()->where('title', 'More shelter beds')->value('key'))
                    ->and($updates->firstWhere('type', 'article')['link'])->toBe('https://news.example/fresh#:~:text=Example')
                    ->and($older['planks'])->toBe(['More shelter beds'])
                    ->and($older['tab'])->toBe('platform')
                    ->and($updates->firstWhere('type', 'article')['title'])->toBe('Fresh story')
                    ->and($updates->pluck('at')->all())->toBe($updates->pluck('at')->sortDesc()->values()->all());
            });
    });
});
