<?php

use Cultpantry\Elections\Actions\BuildUpdateDigest;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Notifications\ElectionsUpdateNotification;

describe('Update digest', function () {
    $import = fn (string $xml) => app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election>'.$xml.'</election>');
    $entry = fn (string $summary, string $url, string $kind = 'statement', string $source = 'Example News') => "<entry><kind>{$kind}</kind><topic>housing</topic><summary>{$summary}</summary><source_url>{$url}</source_url><source_type>news</source_type><source_name>{$source}</source_name></entry>";
    $candidate = fn (string $name, string $inner = '') => "<candidate><name>{$name}</name><office>councillor</office><status>nominated</status>{$inner}</candidate>";
    $digest = fn ($since) => app(BuildUpdateDigest::class)->handle($since, now()->addMinute());

    it('tells a news series as one item naming its candidates, and other stories with a short summary', function () use ($import, $candidate, $digest) {
        $since = now()->subMinute();
        $profile = fn (string $name, int $n) => "<article><title>ELECTION 2026: Salmon Arm city council candidate profile for {$name}</title><url>https://castanet.example/{$n}</url><outlet>Castanet</outlet><candidates><candidate>{$name}</candidate></candidates></article>";
        $import('<candidates>'.$candidate('Jane Example').$candidate('Sam Sample').'</candidates><articles>'
            .$profile('Jane Example', 1).$profile('Sam Sample', 2)
            .'<article><title>Advance voting begins</title><url>https://castanet.example/3</url><outlet>Castanet</outlet><summary>Polls are open at the Fifth Avenue centre from 8 a.m. to 8 p.m.</summary></article></articles>');

        $news = collect($digest($since)['news']);

        expect($news)->toHaveCount(2)
            ->and($news[0])->toBe(['title' => 'ELECTION 2026: Salmon Arm city council candidate profile', 'url' => null, 'outlet' => null, 'text' => 'Castanet added 2 to its series: Jane Example and Sam Sample.', 'links' => ['Jane Example' => 'https://castanet.example/1', 'Sam Sample' => 'https://castanet.example/2']])
            ->and($news[1])->toBe(['title' => 'Advance voting begins', 'url' => 'https://castanet.example/3', 'outlet' => 'Castanet', 'text' => 'Polls are open at the Fifth Avenue centre from 8 a.m. to 8 p.m.']);
    });

    it('tells a source many candidates answered once, and gives each other candidate one sentence', function () use ($import, $entry, $candidate, $digest) {
        $since = now()->subMinute();
        $qa = 'https://observer.example/strategic-priorities';
        $import('<candidates>'
            .$candidate('Jane Example', '<entries>'.$entry('Jane on the plan.', $qa).$entry('Says the pool should move into the short term.', 'https://site.example/jane', 'statement', 'janeexample.ca').$entry('Was president of the tennis club.', 'https://site.example/jane2', 'background', 'janeexample.ca').'</entries>'
                .'<planks><plank><key>rec</key><title>Recreation first</title><tier>top</tier><rank>1</rank><rationale>r</rationale><entries>'.$entry('Rec.', 'https://site.example/rec', 'plank', 'janeexample.ca').'</entries></plank></planks>')
            .$candidate('Sam Sample', '<entries>'.$entry('Sam on the plan.', $qa).$entry('Was president of the tennis club.', 'https://site.example/sam', 'background').'</entries>')
            .$candidate('Alex Quiet', '<entries>'.$entry('Alex on the plan.', $qa).'</entries>')
            .'</candidates><articles><article><title>Candidates speak to strategic priorities</title><url>'.$qa.'</url><outlet>Observer</outlet></article></articles>');

        $d = $digest($since);

        expect($d['news'])->toBe([])
            ->and($d['shared'])->toBe([['title' => 'Candidates speak to strategic priorities', 'url' => $qa, 'outlet' => 'Observer', 'text' => '3 candidates answered: Jane Example, Sam Sample and Alex Quiet.']])
            // Positions before background; Jane's plank source statement is the
            // top-ranked item, and her new plank is named.
            ->and($d['highlights'])->toBe([
                '**Jane Example:** Rec. New on their platform: Recreation first.',
                '**Sam Sample** was president of the tennis club.',
            ])
            ->and($d['also'])->toBe([]);
    });

    it('gives at most one sentence per candidate and names the rest', function () use ($import, $entry, $candidate, $digest) {
        $since = now()->subMinute();
        $people = collect(range(1, BuildUpdateDigest::HIGHLIGHTS + 2))->map(fn ($i) => $candidate("Person {$i}", '<entries>'.$entry("Says thing {$i}.", "https://x.example/{$i}").$entry("Says another {$i}.", "https://x.example/{$i}b").'</entries>'));
        $import('<candidates>'.$people->implode('').'</candidates>');

        $d = $digest($since);

        expect($d['highlights'])->toHaveCount(BuildUpdateDigest::HIGHLIGHTS)
            ->and($d['also'])->toHaveCount(2)
            ->and(collect($d['highlights'])->every(fn ($s) => str_starts_with($s, '**Person ')))->toBeTrue();
    });

    it('reads a summary that opens with an -ing phrase as a sentence, and never ends an excerpt in doubled dots', function () use ($import, $entry, $candidate, $digest) {
        $since = now()->subMinute();
        $long = 'Says the school should adopt a policy at J.L. '.str_repeat('and more detail ', 20);
        $import('<candidates>'
            .$candidate('Jane Example', '<entries>'.$entry('Drawing on her committee work, describes a drier city.', 'https://x.example/j').'</entries>')
            .$candidate('Sam Sample', '<entries>'.$entry($long, 'https://x.example/s').'</entries>')
            .'</candidates>');

        $h = collect($digest($since)['highlights']);

        expect($h)->toContain('**Jane Example**, drawing on her committee work, describes a drier city.')
            ->and($h->first(fn ($s) => str_starts_with($s, '**Sam Sample**')))->toEndWith('...')->not->toContain('....');
    });

    it('links each candidate in a series item to their own story', function () {
        $mail = (new ElectionsUpdateNotification([
            'articles' => 2, 'events' => 0, 'candidates' => 0, 'total' => 2,
            'news' => [['title' => 'Candidate profile', 'url' => null, 'outlet' => null, 'text' => 'Castanet added 2 to its series: Jane Example and Sam Sample.', 'links' => ['Jane Example' => 'https://c.example/1', 'Sam Sample' => 'https://c.example/2']]],
        ]))->toMail(new stdClass);

        expect(implode("\n", $mail->introLines))->toContain('Castanet added 2 to its series: [Jane Example](https://c.example/1) and [Sam Sample](https://c.example/2).');
    });

    it('lays the email out as sections with the content in them', function () {
        $mail = (new ElectionsUpdateNotification([
            'articles' => 1, 'events' => 0, 'candidates' => 3, 'total' => 9,
            'news' => [['title' => 'Advance voting begins', 'url' => 'https://castanet.example/3', 'outlet' => 'Castanet', 'text' => 'Polls are open.']],
            'more_news' => 0, 'coming_up' => [],
            'shared' => [['title' => 'Strategic priorities', 'url' => 'https://observer.example/q', 'outlet' => 'Observer', 'text' => '3 candidates answered: A, B and C.']],
            'highlights' => ['**Jane Example** says the pool should move into the short term.'],
            'also' => ['Sam Sample', 'Alex Quiet'],
        ]))->toMail(new stdClass);
        $body = implode("\n", $mail->introLines);

        expect($body)->toContain('**In the news**')
            ->toContain('[Advance voting begins](https://castanet.example/3) (Castanet) — Polls are open.')
            ->toContain('**One question, many answers:** [Strategic priorities](https://observer.example/q) (Observer). 3 candidates answered: A, B and C.')
            ->toContain('**Jane Example** says the pool should move into the short term.')
            ->toContain('**Also updated:** Sam Sample and Alex Quiet.');
    });
});
