<?php

use App\Models\User;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;

describe('Elections policies', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->candidate = Candidate::create(['name' => 'Jane Example', 'office' => 'councillor', 'status' => 'declared']);
        $this->entry = $this->candidate->entries()->create([
            'kind' => 'plank',
            'summary' => 'x',
            'source_url' => 'https://example.com',
            'source_type' => 'news',
        ]);
    });

    it('allows admin everything', function () {
        expect($this->admin->can('viewAny', Candidate::class))->toBeTrue()
            ->and($this->admin->can('view', $this->candidate))->toBeTrue()
            ->and($this->admin->can('delete', $this->candidate))->toBeTrue()
            ->and($this->admin->can('import', Candidate::class))->toBeTrue()
            ->and($this->admin->can('export', Candidate::class))->toBeTrue()
            ->and($this->admin->can('delete', $this->entry))->toBeTrue();
    });

    it('denies customers everything', function () {
        expect($this->customer->can('viewAny', Candidate::class))->toBeFalse()
            ->and($this->customer->can('view', $this->candidate))->toBeFalse()
            ->and($this->customer->can('delete', $this->candidate))->toBeFalse()
            ->and($this->customer->can('import', Candidate::class))->toBeFalse()
            ->and($this->customer->can('export', Candidate::class))->toBeFalse()
            ->and($this->customer->can('delete', $this->entry))->toBeFalse();
    });

    it('denies panel users outside a vetted panel request', function () {
        // Gate::before only grants view/viewAny inside a request
        // AdminMiddleware has matched to the user's section.
        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();

        expect($viewer->can('view', $this->candidate))->toBeFalse()
            ->and($viewer->can('delete', $this->candidate))->toBeFalse();
    });
});
