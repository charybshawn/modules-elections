<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A plank: one position a candidate campaigns on, ranked by how much
        // the candidate themselves emphasizes it. The plank-kind entries
        // that state it (website, Q&A, announcement...) hang off it as its
        // sources via elections_entries.plank_id.
        Schema::create('elections_planks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();
            // Stable per-candidate key from the research file (e.g.
            // "downtown-parking") -- the import's match key.
            $table->string('key');
            $table->string('title');
            // Entry::TOPICS.
            $table->string('topic')->default('other');
            $table->text('summary')->nullable();
            // Plank::TIERS, then 1-based rank within the candidate's whole
            // platform: the research pass's judgment of the candidate's own
            // emphasis, explained in rationale.
            $table->string('tier')->default('mentioned');
            $table->unsignedSmallInteger('rank')->default(999);
            $table->text('rationale')->nullable();
            // The signals behind the rank, shown as chips so the ordering
            // is never a black box. Position only when the candidate
            // numbers or lists their own priorities.
            $table->unsignedTinyInteger('priority_position')->nullable();
            $table->boolean('has_commitment')->default(false);
            $table->timestamps();

            $table->unique(['candidate_id', 'key']);
        });

        Schema::table('elections_entries', function (Blueprint $table) {
            $table->foreignId('plank_id')->nullable()->after('candidate_id')->constrained('elections_planks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('elections_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plank_id');
        });
        Schema::dropIfExists('elections_planks');
    }
};
