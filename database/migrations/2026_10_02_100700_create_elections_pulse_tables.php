<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Community Pulse: dated snapshots of what residents are saying in
        // local election discussion, summarized by the community-pulse skill.
        // Aggregates and paraphrases only -- no resident is named or quoted,
        // and the raw comments are never stored.
        Schema::create('elections_pulse_snapshots', function (Blueprint $table) {
            $table->id();
            // One snapshot per pass; the import's match key.
            $table->date('taken_on')->unique();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedSmallInteger('threads_read')->default(0);
            // Distinct people across every thread read.
            $table->unsignedSmallInteger('commenters')->default(0);
            // Where the conversation was read (e.g. the Rant and Rave group).
            $table->string('sources')->nullable();
            $table->text('method_note')->nullable();
            // [{text, issue}] -- headline takeaways, each pointing at the
            // issue key that supports it.
            $table->json('conclusions')->nullable();
            $table->timestamps();
        });

        Schema::create('elections_pulse_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('elections_pulse_snapshots')->cascadeOnDelete();
            // Stable across snapshots so the page can show change over time.
            $table->string('key');
            $table->string('title');
            // Entry::TOPICS -- also how it's matched to candidates' planks.
            $table->string('topic')->default('other');
            // Distinct commenters who raised it: the weight, not comment count.
            $table->unsignedSmallInteger('voices')->default(0);
            // Rough split of those voices, in percent.
            $table->unsignedTinyInteger('support_pct')->nullable();
            $table->unsignedTinyInteger('oppose_pct')->nullable();
            $table->unsignedTinyInteger('mixed_pct')->nullable();
            // PulseIssue::HEAT.
            $table->string('heat')->default('medium');
            $table->text('summary')->nullable();
            $table->json('wants')->nullable();
            $table->json('questions')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'key']);
        });

        Schema::create('elections_pulse_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('elections_pulse_snapshots')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();
            // Plain counts only -- no sentiment.
            $table->unsignedSmallInteger('mentions')->default(0);
            $table->unsignedSmallInteger('commenters')->default(0);
            $table->timestamps();

            $table->unique(['snapshot_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_pulse_mentions');
        Schema::dropIfExists('elections_pulse_issues');
        Schema::dropIfExists('elections_pulse_snapshots');
    }
};
