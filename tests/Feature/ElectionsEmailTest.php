<?php

require_once __DIR__.'/fixtures.php';

use Cultpantry\Elections\Actions\BuildElectionsEmail;
use Cultpantry\Elections\Actions\ImportElectionFromXml;
use Cultpantry\Elections\Models\ChangelogEntry;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Notifications\ElectionsUpdateNotification;

/*
 * The update email: both elections in one message, in the module's own template.
 */

function emailImport(string $inner, string $election): array
{
    return app(ImportElectionFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><election id="'.$election.'">'.$inner.'</election>');
}

describe('update email', function () {
    $build = fn ($since) => app(BuildElectionsEmail::class)->handle($since, now()->addMinute());

    it('has a section for each election with something new, the nearer voting day first', function () use ($build) {
        $since = now()->subMinute();
        ElectionEvent::create(['election_id' => Election::municipal()->id, 'title' => 'General voting day', 'kind' => 'general_voting', 'starts_at' => now()->addDays(5)]);
        ElectionEvent::create(['election_id' => Election::provincial()->id, 'title' => 'Final Voting Day', 'kind' => 'general_voting', 'starts_at' => now()->addDays(12)]);
        emailImport('<articles><article><title>Provincial story</title><url>https://n.example/p</url><outlet>Out</outlet></article></articles>', Election::PROVINCIAL);
        emailImport('<articles><article><title>City story</title><url>https://n.example/c</url><outlet>Out</outlet></article></articles>', Election::MUNICIPAL);

        $email = $build($since);

        expect(collect($email['sections'])->pluck('key')->all())->toBe(['municipal', 'provincial'])
            ->and(collect($email['voting_days'])->pluck('days_away')->all())->toBe([5, 12])
            // Each election's stories stay in its own section.
            ->and($email['sections'][0]['digest']['news'][0]['title'])->toBe('City story')
            ->and($email['sections'][1]['digest']['news'][0]['title'])->toBe('Provincial story');
    });

    it('leaves out an election with nothing new, and a quiet window sends nothing', function () use ($build) {
        emailImport('<articles><article><title>City story</title><url>https://n.example/c</url><outlet>Out</outlet></article></articles>', Election::MUNICIPAL);
        $email = $build(now()->subMinute());
        expect(collect($email['sections'])->pluck('key')->all())->toBe(['municipal']);

        expect($build(now()->addHour())['total'])->toBe(0);
    });

    it('adds the provincial extras: polls with the average and its movement, the riding projection, fact-checks and party news', function () use ($build) {
        $since = now()->subMinute();
        emailImport('<parties><party><name>BC NDP</name><as_of>2026-10-12</as_of><claims><claim><key>k</key><claim>Jobs are up.</claim><source_url>https://p.example</source_url><rating>holds_up</rating><summary>s</summary><checked_on>2026-10-12</checked_on></claim></claims>'
            .'<news><article><title>NDP promises a freeze</title><url>https://n.example/n</url><outlet>Daily</outlet></article></news></party></parties>'
            .'<polls><poll><pollster>Acme</pollster><fielded_to>'.now()->toDateString().'</fielded_to><results><result><party>BC NDP</party><percent>37</percent></result><result><party>Conservative Party</party><percent>43</percent></result></results><source_url>https://poll.example/a</source_url></poll></polls>'
            .'<projections><projection><riding>'.Election::provincial()->riding.'</riding><rating>safe</rating><as_of>2026-10-11</as_of><source_url>https://338.example/x</source_url><shares><share><party>Conservative Party</party><percent>55</percent></share><share><party>BC NDP</party><percent>27</percent></share></shares></projection></projections>', Election::PROVINCIAL);

        $x = collect($build($since)['sections'])->firstWhere('key', 'provincial')['provincial'];

        expect($x['total'])->toBe(3)
            ->and($x['polls'][0]['pollster'])->toBe('Acme')
            ->and($x['average']['parties']['Conservative Party']['percent'])->toEqual(43)
            // No poll existed before this window, so there is no movement to report yet.
            ->and($x['average']['change']['Conservative Party'])->toBeNull()
            ->and($x['projection']['rating'])->toBe('safe')
            ->and($x['projection']['leaders'])->toBe(['Conservative Party' => 1])
            ->and($x['fact_checks'][0]['rating_label'])->toBe('Holds up')
            ->and($x['fact_checks'][0]['url'])->toContain('tab=fact-check')
            ->and($x['party_news'][0]['party'])->toBe('BC NDP');
    });

    it('picks change-log entries by when their commits fell, between the last email and this one', function () use ($build) {
        $entry = fn (string $key, $at, int $impact = 2, string $page = 'all') => ChangelogEntry::create(['key' => $key, 'committed_at' => $at, 'title' => $key, 'body' => 'b', 'scope' => 'both', 'impact' => $impact, 'page' => $page]);
        $entry('before', now()->subHours(5), 5);
        $entry('inside', now()->subHours(2));
        $entry('after', now()->addHours(2), 5);

        $email = $build(now()->subHours(3));

        expect(collect($email['changelog'])->pluck('title')->all())->toBe(['inside'])
            // A change-log entry alone is enough to send an email.
            ->and($email['total'])->toBe(1);
    });

    it('lists the change log with the biggest effect on the experience first, newest first among equals, each tagged with its page', function () use ($build) {
        $entry = fn (string $key, $at, int $impact, string $page) => ChangelogEntry::create(['key' => $key, 'committed_at' => $at, 'title' => $key, 'body' => 'b', 'scope' => 'both', 'impact' => $impact, 'page' => $page]);
        $entry('small-new', now()->subMinutes(5), 1, 'hub');
        $entry('big-old', now()->subHours(3), 5, 'provincial');
        $entry('mid-new', now()->subMinutes(10), 3, 'party');
        $entry('mid-old', now()->subHours(1), 3, 'polls');

        $log = $build(now()->subHours(4))['changelog'];

        expect(collect($log)->pluck('title')->all())->toBe(['big-old', 'mid-new', 'mid-old', 'small-new'])
            ->and(collect($log)->pluck('page_label')->all())->toBe(['BC provincial dashboard', 'Party pages', 'Poll history', 'Elections home'])
            ->and($log[0]['impact_label'])->toBe('Big');
    });

    it('renders the template with the countdowns, each section and the change log, escaping what it prints', function () {
        $html = emailHtml(emailWith([
            'digest' => ['news' => [['title' => '<script>alert(1)</script> Story', 'url' => 'https://n.example/s', 'outlet' => 'Out', 'text' => 'Fine.']]],
            'changelog' => [['title' => 'Party pages', 'body' => 'Each party gets a page.', 'scope' => 'provincial', 'scope_label' => 'BC provincial election', 'page' => 'party', 'page_label' => 'Party pages', 'impact' => 4, 'impact_label' => 'Major', 'committed_at' => now()->toIso8601String(), 'url' => null]],
        ]));

        expect($html)->toContain('Salmon Arm voting day')->toContain('5 days away')
            ->toContain('Salmon Arm municipal election')
            ->toContain("What's new on the site")->toContain('Each party gets a page.')
            ->toContain(route('admin.elections.changelog'))
            ->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;');
    });

    it('has a subject naming each election with its count, and a plain-text version', function () {
        $email = emailWith();
        $mail = (new ElectionsUpdateNotification($email))->toMail(new stdClass);

        expect($mail->subject)->toBe('Election update: 1 for Salmon Arm')
            ->and($mail->view)->toBe(['elections::mail.update', 'elections::mail.update-text']);

        $text = view('elections::mail.update-text', ['email' => $email, 'p' => new \Cultpantry\Elections\Support\EmailPresenter])->render();
        expect($text)->toContain('ELECTION UPDATE')->toContain('SALMON ARM MUNICIPAL ELECTION')->not->toContain('<');
    });

    it('imports change-log entries once, matches them on key, and rejects one with no commit time', function () {
        $xml = fn (string $title) => '<changelog><entry><key>k</key><committed_at>2026-10-11T22:32:50-07:00</committed_at><title>'.$title.'</title><body>b</body><scope>provincial</scope><page>party</page><impact>4</impact><commits>c48c4bd</commits></entry></changelog>';
        expect(emailImport($xml('First'), Election::PROVINCIAL)['changelog'])->toBe(['created' => 1, 'updated' => 0, 'unchanged' => 0]);
        expect(emailImport($xml('Second'), Election::PROVINCIAL)['changelog'])->toBe(['created' => 0, 'updated' => 1, 'unchanged' => 0])
            ->and(ChangelogEntry::first()->title)->toBe('Second')
            ->and(ChangelogEntry::first()->only(['page', 'impact']))->toBe(['page' => 'party', 'impact' => 4]);

        $bad = emailImport('<changelog><entry><key>x</key><title>t</title><body>b</body></entry></changelog>', Election::PROVINCIAL);
        expect($bad['changelog']['created'])->toBe(0)->and($bad['problems'])->not->toBeEmpty();

        // An unknown page or an impact outside 1-5 falls back, and says so.
        $odd = emailImport('<changelog><entry><key>y</key><committed_at>2026-10-11T22:32:50-07:00</committed_at><title>t</title><body>b</body><page>nowhere</page><impact>9</impact></entry></changelog>', Election::PROVINCIAL);
        expect($odd['problems'])->toHaveCount(2)->and(ChangelogEntry::where('key', 'y')->first()->only(['page', 'impact']))->toBe(['page' => 'all', 'impact' => 2]);
    });

    it('serves the change-log page', function () {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        ChangelogEntry::create(['key' => 'a', 'committed_at' => now(), 'title' => 'Party pages', 'body' => 'b', 'scope' => 'both']);

        $this->actingAs($admin)->get(route('admin.elections.changelog'))
            ->assertOk()
            ->assertInertia(fn ($page) => expect($page->toArray()['props']['entries'][0]['title'])->toBe('Party pages'));
    });
});
