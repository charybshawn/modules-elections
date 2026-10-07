<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;

describe('School trustee candidates', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><election><candidates>'
        .'<candidate><name>Terry Trustee</name><office>trustee</office><status>nominated</status>'
        .'<entries><entry><kind>statement</kind><topic>education</topic><summary>Wants more school space.</summary>'
        .'<source_url>https://example.com/terry</source_url><source_type>news</source_type></entry></entries></candidate>'
        .'<candidate><name>Cora Council</name><office>councillor</office><status>nominated</status></candidate>'
        .'<candidate><name>Max Mayor</name><office>mayor</office><status>nominated</status></candidate>'
        .'</candidates></election>';

    it('imports trustees, with education-topic statements', function () use ($xml) {
        $result = app(ImportElectionFromXml::class)->handleString($xml);

        expect($result['problems'])->toBe([])
            ->and(Candidate::where('name', 'Terry Trustee')->value('office'))->toBe('trustee')
            ->and(Entry::where('topic', 'education')->count())->toBe(1);
    });

    it('lists mayor, then council, then trustees on the dashboard', function () use ($xml) {
        app(ImportElectionFromXml::class)->handleString($xml);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('candidates.0.name', 'Max Mayor')
                ->where('candidates.1.name', 'Cora Council')
                ->where('candidates.2.name', 'Terry Trustee')
                ->where('options.offices.trustee', 'School trustee')
                ->where('options.topics.education', 'Education & schools'));
    });
});
