<?php

use App\Models\User;
use Cultpantry\Elections\Actions\ExportElectionToXml;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\Candidate;

describe('Candidate photos', function () {
    it('accepts a photo stored in the app, with its credit, and refuses protocol-relative or junk paths', function () {
        Candidate::create(['name' => 'Jane Example', 'office' => 'councillor', 'status' => 'nominated']);
        Candidate::create(['name' => 'Sam Sample', 'office' => 'councillor', 'status' => 'nominated']);

        $result = app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election><candidates>'
            .'<candidate><name>Jane Example</name><photo_url>/images/elections/candidates/jane-example.jpg</photo_url><photo_credit>Friday AM</photo_credit></candidate>'
            .'<candidate><name>Sam Sample</name><photo_url>//evil.example/x.jpg</photo_url></candidate>'
            .'</candidates></election>');

        $jane = Candidate::where('name', 'Jane Example')->first();
        expect($jane->photo_url)->toBe('/images/elections/candidates/jane-example.jpg')
            ->and($jane->photo_credit)->toBe('Friday AM')
            ->and(Candidate::where('name', 'Sam Sample')->value('photo_url'))->toBeNull()
            ->and($result['problems'])->toHaveCount(1);

        expect(app(ImportElectionFromXml::class)->handleString(app(ExportElectionToXml::class)->handle())['problems'])->toBe([]);
    });

    it('sends the photo and its credit to the candidate page', function () {
        $jane = Candidate::create(['name' => 'Jane Example', 'office' => 'councillor', 'status' => 'nominated', 'photo_url' => '/images/elections/candidates/jane-example.jpg', 'photo_credit' => 'Friday AM']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.candidates.show', $jane->slug))
            ->assertInertia(fn ($page) => $page
                ->where('portfolio.candidate.photo_url', '/images/elections/candidates/jane-example.jpg')
                ->where('portfolio.candidate.photo_credit', 'Friday AM'));
    });

    it('has a photo file in the app for every candidate photo on file', function () {
        $files = glob(public_path('images/elections/candidates/*.jpg'));

        // 16 Friday AM, Harrison's City portrait, 6 cropped from Observer composites, Riley's campaign site.
        expect($files)->toHaveCount(24);
    });
});
