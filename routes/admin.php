<?php

use Cultpantry\Elections\Http\Controllers\Admin\ArticleController;
use Cultpantry\Elections\Http\Controllers\Admin\CandidateController;
use Cultpantry\Elections\Http\Controllers\Admin\CommunityPulseController;
use Cultpantry\Elections\Http\Controllers\Admin\ElectionEventController;
use Cultpantry\Elections\Http\Controllers\Admin\EntryController;
use Cultpantry\Elections\Http\Controllers\Admin\PlankController;
use Illuminate\Support\Facades\Route;

// 'web' is REQUIRED here and is not optional. Core routes/web.php gets the
// 'web' middleware group automatically via bootstrap/app.php's
// withRouting(web: ...); routes loaded via loadRoutesFrom() from a
// provider do NOT get it automatically. Omitting it means no session is
// started, so 'auth' silently treats every request as a guest and
// redirects to /login -- even for a logged-in admin. Do not drop 'web'
// from this array.
//
// Every path stays under /admin/elections so AdminMiddleware maps it to the
// Elections section for section-restricted (invited) users.
//
// No create/edit routes on purpose: all data arrives through the research
// skill's XML import. The deletes are the only manual writes -- the import
// never deletes, so they're how a bad or irrelevant find gets removed.
Route::prefix('admin')->name('admin.')->middleware(['web', 'auth', 'admin'])->group(function () {
    Route::prefix('elections')->name('elections.')->group(function () {
        Route::get('/', [CandidateController::class, 'index'])->name('index');
        Route::post('import', [CandidateController::class, 'import'])->name('import');
        Route::get('export', [CandidateController::class, 'export'])->name('export');

        Route::get('candidates/{candidate}', [CandidateController::class, 'show'])->name('candidates.show');
        Route::delete('candidates/{candidate}', [CandidateController::class, 'destroy'])->name('candidates.destroy');
        Route::delete('candidates/{candidate}/entries/{entry}', [EntryController::class, 'destroy'])->name('entries.destroy');
        Route::delete('candidates/{candidate}/planks/{plank}', [PlankController::class, 'destroy'])->name('planks.destroy');

        Route::delete('events/{event}', [ElectionEventController::class, 'destroy'])->name('events.destroy');

        Route::get('pulse', [CommunityPulseController::class, 'index'])->name('pulse.index');
        Route::delete('pulse/{snapshot}', [CommunityPulseController::class, 'destroy'])->name('pulse.destroy');
        Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');
    });
});
