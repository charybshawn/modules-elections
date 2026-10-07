<?php

use App\Models\Event;
use App\Models\User;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Illuminate\Http\UploadedFile;

/*
 * Deletions and imports reach the host's Event log through Support\Audit.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('records deletions', function () {
    $candidate = Candidate::create(['name' => 'Jane Example', 'office' => 'councillor', 'status' => 'nominated']);
    $entry = $candidate->entries()->create(['kind' => 'statement', 'summary' => 'Said a thing.', 'source_url' => 'https://example.com/a', 'source_type' => 'news']);
    $article = Article::create(['title' => 'Story', 'url' => 'https://example.com/story']);
    $event = ElectionEvent::create(['title' => 'Forum', 'kind' => 'forum', 'starts_at' => '2026-10-10 19:00:00']);

    $this->actingAs($this->admin)->delete(route('admin.elections.entries.destroy', [$candidate, $entry]));
    $this->actingAs($this->admin)->delete(route('admin.elections.articles.destroy', $article));
    $this->actingAs($this->admin)->delete(route('admin.elections.events.destroy', $event));
    $this->actingAs($this->admin)->delete(route('admin.elections.candidates.destroy', $candidate));

    expect(Event::pluck('type')->all())->toContain('elections.entry_deleted', 'elections.article_deleted', 'elections.event_deleted', 'elections.candidate_deleted')
        ->and(Event::where('type', 'elections.candidate_deleted')->sole()->actor_id)->toBe($this->admin->id);
});

it('records an XML import as one entry with its file and summary', function () {
    $xml = '<?xml version="1.0"?><election><candidates><candidate><name>Jane Example</name><office>councillor</office></candidate></candidates></election>';

    $this->actingAs($this->admin)->post(route('admin.elections.import'), [
        'file' => UploadedFile::fake()->createWithContent('research.xml', $xml),
    ]);

    $import = Event::where('type', 'elections.import')->sole();
    expect($import->metadata['file'])->toBe('research.xml')
        ->and($import->metadata['summary'])->toContain('candidates: 1 new');
});
