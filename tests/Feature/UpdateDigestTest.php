<?php

use Cultpantry\Elections\Actions\BuildUpdateDigest;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Notifications\ElectionsUpdateNotification;

describe('Update digest', function () {
    $import = fn (string $xml) => app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election>'.$xml.'</election>');
    $entry = fn (string $summary, string $url, string $kind = 'statement', string $source = 'Example News') => "<entry><kind>{$kind}</kind><topic>housing</topic><summary>{$summary}</summary><source_url>{$url}</source_url><source_type>news</source_type><source_name>{$source}</source_name></entry>";
    $candidate = fn (string $name, string $inner = '') => "<candidate><name>{$name}</name><office>councillor</office><status>nominated</status>{$inner}</candidate>";

    it('groups a news series into one line that names its candidates', function () use ($import, $candidate) {
        $since = now()->subMinute();
        $profile = fn (string $name, int $n) => "<article><title>ELECTION 2026: Salmon Arm city council candidate profile for {$name}</title><url>https://castanet.example/{$n}</url><outlet>Castanet</outlet><candidates><candidate>{$name}</candidate></candidates></article>";
        $import('<candidates>'.$candidate('Jane Example').$candidate('Sam Sample').'</candidates><articles>'
            .$profile('Jane Example', 1).$profile('Sam Sample', 2)
            .'<article><title>Advance voting begins</title><url>https://castanet.example/3</url><outlet>Castanet</outlet></article></articles>');

        $digest = app(BuildUpdateDigest::class)->handle($since, now()->addMinute());
        $news = collect($digest['top'])->where('label', 'News (Castanet)')->values();

        expect($news)->toHaveCount(2)
            ->and($news[0]['text'])->toBe('ELECTION 2026: Salmon Arm city council candidate profile (2): Jane Example, Sam Sample')
            ->and($news[1]['text'])->toBe('Advance voting begins')
            ->and($news[1]['url'])->toBe('https://castanet.example/3')
            ->and($digest['articles'])->toBe(3);
    });

    it('gives one line to a source shared by several candidates, and one synopsis line to each candidate', function () use ($import, $entry, $candidate) {
        $since = now()->subMinute();
        $qa = 'https://observer.example/strategic-priorities';
        $import('<candidates>'
            .$candidate('Jane Example', '<entries>'.$entry('Jane on the plan.', $qa).$entry('Jane elsewhere.', 'https://site.example/jane', 'statement', 'janeexample.ca').$entry('Jane again.', 'https://site.example/jane2', 'statement', 'janeexample.ca').'</entries>'
                .'<planks><plank><key>rec</key><title>Recreation first</title><tier>top</tier><rank>1</rank><rationale>r</rationale><entries>'.$entry('Rec.', 'https://site.example/rec', 'plank', 'janeexample.ca').'</entries></plank></planks>')
            .$candidate('Sam Sample', '<entries>'.$entry('Sam on the plan.', $qa).'</entries>')
            .$candidate('Alex Quiet', '<entries>'.$entry('Alex on the plan.', $qa).'</entries>')
            .'</candidates><articles><article><title>Candidates speak to strategic priorities</title><url>'.$qa.'</url><outlet>Observer</outlet></article></articles>');

        $lines = collect(app(BuildUpdateDigest::class)->handle($since, now()->addMinute())['top']);

        // The shared source is also a new story, so it rides on the story's line.
        $shared = $lines->where('url', $qa)->values();
        expect($shared)->toHaveCount(1)
            ->and($shared[0]['label'])->toBe('News (Observer)')
            ->and($shared[0]['text'])->toBe('Candidates speak to strategic priorities — 3 candidates: Jane Example, Sam Sample, Alex Quiet');

        $jane = $lines->where('label', 'Jane Example')->values();
        expect($jane)->toHaveCount(1)
            ->and($jane[0]['text'])->toBe('1 new platform point (Recreation first), 2 statements, 1 platform statement, from janeexample.ca')
            ->and($lines->where('label', 'Sam Sample'))->toBeEmpty();
    });

    it('labels a shared source that is not a new story by its source name', function () use ($import, $entry, $candidate) {
        $since = now()->subMinute();
        $survey = 'https://survey.example/climate';
        $import('<candidates>'
            .$candidate('Jane Example', '<entries>'.$entry('Jane on climate.', $survey, 'qa_answer', 'Climate survey').'</entries>')
            .$candidate('Sam Sample', '<entries>'.$entry('Sam on climate.', $survey, 'qa_answer', 'Climate survey').'</entries>')
            .$candidate('Alex Quiet', '<entries>'.$entry('Alex on climate.', $survey, 'qa_answer', 'Climate survey').'</entries>')
            .'</candidates>');

        $lines = collect(app(BuildUpdateDigest::class)->handle($since, now()->addMinute())['top']);

        expect($lines)->toHaveCount(1)
            ->and($lines[0])->toBe(['label' => 'Climate survey', 'text' => '3 candidates: Jane Example, Sam Sample, Alex Quiet', 'url' => $survey]);
    });

    it('counts the lines left over, not the raw items, in the email', function () {
        $top = collect(range(1, 10))->map(fn ($i) => ['label' => "Candidate {$i}", 'text' => 'stuff', 'url' => null])->all();
        $mail = (new ElectionsUpdateNotification(['articles' => 0, 'events' => 0, 'candidates' => 12, 'total' => 80, 'top' => $top, 'more' => 2]))->toMail(new stdClass);

        expect(implode("\n", $mail->introLines))->toContain('...and 2 more.')->not->toContain('70 more');
    });
});
