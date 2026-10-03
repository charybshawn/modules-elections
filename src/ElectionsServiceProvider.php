<?php

namespace Cultpantry\Elections;

use App\Support\AdminNav;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Policies\AdminWritePolicy;
use Cultpantry\Elections\Policies\CandidatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ElectionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 1. Admin routes -- additive merge into the existing admin route group.
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');

        // 2. Migrations for this module's own elections_* tables.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 3. Nav entry. The item key is Str::slug('Elections') = 'elections',
        //    which is what per-user section grants (and invitations) store --
        //    keep the name pinned, renaming it orphans those grants. Every
        //    URL this module serves sits under 'match', so AdminMiddleware
        //    can map each request to this section.
        AdminNav::register([
            'name' => 'Elections',
            'href' => '/admin/elections',
            'icon' => 'elections',
            'match' => '/admin/elections',
            'module' => 'cultpantry/elections',
        ]);

        // 4. Policies -- explicit, since auto-discovery only scans App\Models.
        //    Invited read-only viewers pass view/viewAny through the host's
        //    Gate::before; every write ability stays admin-only here.
        Gate::policy(Candidate::class, CandidatePolicy::class);
        Gate::policy(Entry::class, AdminWritePolicy::class);
        Gate::policy(Plank::class, AdminWritePolicy::class);
        Gate::policy(Article::class, AdminWritePolicy::class);
        Gate::policy(ElectionEvent::class, AdminWritePolicy::class);

        // 5. Publish the Vue source into resources/js/Pages/Vendor/elections/.
        //    php artisan vendor:publish --tag=elections-pages
        $this->publishes([
            __DIR__.'/../resources/js/Pages' => resource_path('js/Pages/Vendor/elections'),
        ], 'elections-pages');
    }
}
