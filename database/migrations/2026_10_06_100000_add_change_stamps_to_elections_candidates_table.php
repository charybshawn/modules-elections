<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When a candidate's public profile (bio, links, status...) and their
        // admin-only research notes last changed, kept apart from updated_at
        // so that editing the notes doesn't tell every viewer the profile
        // changed. Maintained by Candidate's saving hook.
        Schema::table('elections_candidates', function (Blueprint $table) {
            $table->timestamp('profile_changed_at')->nullable()->after('notes');
            $table->timestamp('notes_changed_at')->nullable()->after('profile_changed_at');
        });

        // Start from what we know: the profile counts from when the candidate
        // was created (earlier edits can't be told apart from notes edits), and
        // the notes from the last save of a candidate that has any.
        DB::table('elections_candidates')->update([
            'profile_changed_at' => DB::raw('created_at'),
            'notes_changed_at' => DB::raw('case when notes is null then created_at else updated_at end'),
        ]);
    }

    public function down(): void
    {
        Schema::table('elections_candidates', function (Blueprint $table) {
            $table->dropColumn(['profile_changed_at', 'notes_changed_at']);
        });
    }
};
