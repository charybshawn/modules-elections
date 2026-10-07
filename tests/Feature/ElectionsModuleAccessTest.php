<?php

use App\Actions\UpdateSiteSetting;
use App\Models\User;
use App\Support\AdminNav;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

function electionsViewer(array $sections = ['elections']): User
{
    $user = User::factory()->create(['role' => 'customer']);
    $user->forceFill(['admin_permissions' => $sections])->save();

    return $user->fresh();
}

function electionsCandidate(array $attributes = []): Candidate
{
    $candidate = Candidate::create([
        'name' => 'Jane Example',
        'office' => 'councillor',
        'status' => 'nominated',
        'notes' => 'Two sources disagree on her occupation.',
        ...$attributes,
    ]);
    $candidate->entries()->create([
        'kind' => 'plank',
        'topic' => 'housing',
        'summary' => 'Supports more secondary suites.',
        'source_url' => 'https://example.com/platform',
        'source_type' => 'candidate_site',
    ]);

    return $candidate;
}

describe('elections admin module', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = User::factory()->create(['role' => 'customer']);
    });

    it('registers its routes with the web middleware group', function () {
        expect(Route::getRoutes()->getByName('admin.elections.index')->middleware())->toContain('web');
    });

    it('registers an Elections nav item under the elections key', function () {
        expect(collect(AdminNav::all())->pluck('key'))->toContain('elections');
    });

    it('renders the dashboard and a candidate portfolio for an admin', function () {
        $candidate = electionsCandidate();

        $this->actingAs($this->admin)
            ->get(route('admin.elections.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Index')
                ->has('candidates', 1)
                ->where('candidates.0.entries_count', 1));

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', $candidate))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Candidates/Show')
                ->where('portfolio.candidate.notes', 'Two sources disagree on her occupation.')
                ->has('portfolio.sections', 0)
                ->where('portfolio.platform.unranked.0.summary', 'Supports more secondary suites.'));
    });

    it('groups background entries under About rather than as a section', function () {
        $candidate = electionsCandidate();
        foreach ([['local_roots', 'Moved to Salmon Arm in 2006.'], ['career', 'Works as a GIS analyst.']] as [$topic, $summary]) {
            $candidate->entries()->create([
                'kind' => 'background',
                'topic' => $topic,
                'summary' => $summary,
                'source_url' => 'https://example.com/about',
                'source_type' => 'candidate_site',
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', $candidate))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('portfolio.background', 2)
                ->where('portfolio.background.0.topic', 'career')
                ->where('portfolio.background.1.topic', 'local_roots')
                ->has('portfolio.sections', 0)
                ->has('portfolio.platform.unranked', 1)
                ->where('options.backgroundTopics.public_service', 'Public service'));
    });

    it('gives the pages what they need to show new information since a visit', function () {
        $candidate = electionsCandidate();
        $article = Article::create(['title' => 'Story', 'url' => 'https://example.com/story']);
        $article->candidates()->attach($candidate);

        $this->actingAs($this->admin)
            ->get(route('admin.elections.index'))
            ->assertInertia(fn ($page) => $page->where('now', fn ($now) => is_int($now)));

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', $candidate))
            ->assertInertia(fn ($page) => $page
                ->where('portfolio.platform.unranked.0.added_at', fn ($at) => is_string($at))
                ->where('portfolio.articles.0.linked_at', fn ($at) => is_string($at))
                ->where('portfolio.now', fn ($now) => is_int($now)));
    });

    it('opens the tab named in ?tab= and falls back to About for anything else', function () {
        $candidate = electionsCandidate();

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', ['candidate' => $candidate, 'tab' => 'platform']))
            ->assertInertia(fn ($page) => $page->where('activeTab', 'platform'));

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', ['candidate' => $candidate, 'tab' => 'finance']))
            ->assertInertia(fn ($page) => $page->where('activeTab', 'about'));

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', $candidate))
            ->assertInertia(fn ($page) => $page->where('activeTab', 'about'));
    });

    it('offers the research notes tab to admins only', function () {
        $candidate = electionsCandidate();

        $this->actingAs($this->admin)
            ->get(route('admin.elections.candidates.show', ['candidate' => $candidate, 'tab' => 'notes']))
            ->assertInertia(fn ($page) => $page->where('activeTab', 'notes'));

        $this->actingAs(electionsViewer())
            ->get(route('admin.elections.candidates.show', ['candidate' => $candidate, 'tab' => 'notes']))
            ->assertInertia(fn ($page) => $page
                ->where('activeTab', 'about')
                ->missing('portfolio.candidate.notes'));
    });

    it('keeps customers without a grant out', function () {
        $this->actingAs($this->customer)->get(route('admin.elections.index'))->assertForbidden();
    });

    it('redirects guests to login', function () {
        $this->get(route('admin.elections.index'))->assertRedirect(route('login'));
    });

    it('404s for admin when disabled via the Settings toggle, on every controller', function () {
        $candidate = electionsCandidate();
        (new UpdateSiteSetting)->handle('modules.cultpantry/elections.enabled', false);

        $this->actingAs($this->admin)->get(route('admin.elections.index'))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.elections.candidates.show', $candidate))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('admin.elections.candidates.destroy', $candidate))->assertNotFound();

        (new UpdateSiteSetting)->handle('modules.cultpantry/elections.enabled', true);
    });

    it('lets an admin delete entries, articles, events and candidates', function () {
        $candidate = electionsCandidate();
        $entry = $candidate->entries()->first();
        $article = Article::create(['title' => 'Story', 'url' => 'https://example.com/story']);
        $event = ElectionEvent::create(['title' => 'Forum', 'kind' => 'forum', 'starts_at' => '2026-10-07 19:00']);

        $this->actingAs($this->admin)->delete(route('admin.elections.entries.destroy', [$candidate, $entry]))->assertRedirect();
        $this->actingAs($this->admin)->delete(route('admin.elections.articles.destroy', $article))->assertRedirect();
        $this->actingAs($this->admin)->delete(route('admin.elections.events.destroy', $event))->assertRedirect();
        $this->actingAs($this->admin)->delete(route('admin.elections.candidates.destroy', $candidate))->assertRedirect(route('admin.elections.index'));

        expect(Candidate::count())->toBe(0)
            ->and(Article::count())->toBe(0)
            ->and(ElectionEvent::count())->toBe(0);
    });

    it("404s deleting an entry through another candidate's URL", function () {
        $jane = electionsCandidate();
        $other = Candidate::create(['name' => 'Sam Other', 'office' => 'mayor', 'status' => 'declared']);

        $this->actingAs($this->admin)
            ->delete(route('admin.elections.entries.destroy', [$other, $jane->entries()->first()]))
            ->assertNotFound();
    });
});

describe('invited read-only viewers', function () {
    it('can open the dashboard and portfolios', function () {
        $candidate = electionsCandidate();
        $viewer = electionsViewer();

        $this->actingAs($viewer)->get(route('admin.elections.index'))->assertOk();
        $this->actingAs($viewer)
            ->get(route('admin.elections.candidates.show', $candidate))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Candidates/Show')
                // Research notes are admin-only.
                ->missing('portfolio.candidate.notes')
                ->where('admin_access.read_only', true));
    });

    it('cannot import, export or delete anything', function () {
        $candidate = electionsCandidate();
        $viewer = electionsViewer();
        $file = UploadedFile::fake()->createWithContent('e.xml', '<election/>');

        $this->actingAs($viewer)->post(route('admin.elections.import'), ['file' => $file])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.elections.export'))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.elections.candidates.destroy', $candidate))->assertForbidden();
        $this->actingAs($viewer)
            ->delete(route('admin.elections.entries.destroy', [$candidate, $candidate->entries()->first()]))
            ->assertForbidden();

        expect(Candidate::count())->toBe(1);
    });

    it('cannot open the module with a grant for a different section only', function () {
        $this->actingAs(electionsViewer(['categories']))
            ->get(route('admin.elections.index'))
            ->assertRedirect('/admin/categories');
    });

    it('can be granted the section through the permission picker', function () {
        expect(collect(AdminNav::grantableSections())->pluck('key'))->toContain('elections');
    });
});
