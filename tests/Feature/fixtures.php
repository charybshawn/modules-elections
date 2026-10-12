<?php

use Cultpantry\Elections\Actions\ImportElectionFromXml;

/*
 * Shared election fixtures for tests that need the same small field of
 * candidates. Loaded with require_once, so each test file still runs on
 * its own (Pest only collects *Test.php files, so this isn't one).
 */

if (! function_exists('compareFixture')) {
    /**
     * Three councillors: Jane (top-tier homelessness), Sam (also-stated
     * homelessness, top-tier wastewater) and Alex (no planks), with a
     * two-statement scorecard Jane and Sam answered differently on one
     * statement and Alex didn't answer.
     */
    function compareFixture(): void
    {
        $plank = fn (string $key, string $title, string $tier, string $tag) => "<plank><key>{$key}</key><title>{$title}</title><topic>social_services</topic><tier>{$tier}</tier><rank>1</rank><rationale>r</rationale><tags><tag>{$tag}</tag></tags>"
            ."<entries><entry><kind>plank</kind><topic>social_services</topic><summary>{$title}.</summary><source_url>https://example.com/{$key}</source_url><source_type>candidate_site</source_type></entry></entries></plank>";
        $candidate = fn (string $name, string $planks) => "<candidate><name>{$name}</name><office>councillor</office><status>nominated</status><planks>{$planks}</planks></candidate>";
    
        app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election>'
            .'<vocabulary><tag><slug>homelessness</slug><topic>social_services</topic><name>Homelessness</name></tag><tag><slug>wastewater</slug><topic>infrastructure</topic><name>Wastewater treatment</name></tag></vocabulary>'
            .'<candidates>'
            .$candidate('Jane Example', $plank('shelter', 'More shelter beds', 'top', 'homelessness'))
            .$candidate('Sam Sample', $plank('outreach', 'Fund outreach workers', 'also', 'homelessness').$plank('sewage', 'Finish the sewage plant', 'top', 'wastewater'))
            .$candidate('Alex Quiet', '')
            .'</candidates>'
            .'<scorecards><scorecard><key>v4t</key><title>Vote4Tomorrow</title><categories><category><key>b</key><name>Buildings</name><items>'
            .'<item><key>electrify</key><statement>Electrify new buildings.</statement></item><item><key>retrofit</key><statement>Scale up retrofits.</statement></item>'
            .'</items></category></categories><responses>'
            .'<response><candidate>Jane Example</candidate><responded>true</responded><answers><answer item="electrify">supportive</answer><answer item="retrofit">supportive</answer></answers></response>'
            .'<response><candidate>Sam Sample</candidate><responded>true</responded><answers><answer item="electrify">opposed</answer><answer item="retrofit">supportive</answer></answers></response>'
            .'<response><candidate>Alex Quiet</candidate><responded>false</responded></response>'
            .'</responses></scorecard></scorecards></election>');
    }
}

if (! function_exists('emailWith')) {
    /**
     * A minimal update-email array (see BuildElectionsEmail::handle()) with one municipal section; pass
     * ['digest' => [...]] to override that section's digest and ['changelog' => [...]] for change-log entries.
     */
    function emailWith(array $over = []): array
    {
        $digest = array_replace(['articles' => 0, 'events' => 0, 'candidates' => 0, 'total' => 1, 'news' => [], 'more_news' => 0, 'coming_up' => [], 'shared' => [], 'highlights' => [], 'also' => []], $over['digest'] ?? []);

        return [
            'total' => 1,
            'since' => now()->subDay()->toIso8601String(),
            'until' => now()->toIso8601String(),
            'voting_days' => [['key' => 'municipal', 'label' => 'Salmon Arm', 'voting_day' => now()->addDays(5)->toIso8601String(), 'days_away' => 5]],
            'sections' => [['key' => 'municipal', 'title' => 'Salmon Arm municipal election', 'subtitle' => 'Mayor, council and school board', 'url' => 'https://x.example/m', 'voting_day' => null, 'days_away' => 5, 'digest' => $digest, 'provincial' => null, 'total' => 1]],
            'changelog' => $over['changelog'] ?? [],
        ];
    }
}

if (! function_exists('emailHtml')) {
    /** The HTML body of the update email for an email array. */
    function emailHtml(array $email): string
    {
        return (string) (new \Cultpantry\Elections\Notifications\ElectionsUpdateNotification($email))->toMail(new stdClass)->render();
    }
}
