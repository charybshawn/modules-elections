<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Third-party candidate scorecards (e.g. Vote4Tomorrow): a published
        // questionnaire of fixed statements, grouped under the publisher's
        // own category headers, with each candidate's stance on each one.
        Schema::create('elections_scorecards', function (Blueprint $table) {
            $table->id();
            // The import's match key, e.g. vote4tomorrow-2026.
            $table->string('key')->unique();
            $table->string('title');
            $table->string('publisher')->nullable();
            $table->string('url', 2048)->nullable();
            $table->text('about')->nullable();
            $table->date('retrieved_on')->nullable();
            $table->timestamps();
        });

        Schema::create('elections_scorecard_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('elections_scorecards')->cascadeOnDelete();
            $table->string('key');
            // The publisher's header and intro, as they wrote them.
            $table->string('name');
            $table->text('intro')->nullable();
            // Our neutral note on what this area involves in Salmon Arm
            // specifically, with the pages it rests on.
            $table->text('local_context')->nullable();
            $table->json('sources')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['scorecard_id', 'key']);
        });

        Schema::create('elections_scorecard_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('elections_scorecard_categories')->cascadeOnDelete();
            $table->string('key');
            // The publisher's statement, verbatim.
            $table->text('statement');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'key']);
        });

        Schema::create('elections_scorecard_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('elections_scorecards')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();
            // The candidate's page on the publisher's site.
            $table->string('source_url', 2048)->nullable();
            // False when the publisher lists them as not having answered --
            // shown as "did not respond", never as opposition.
            $table->boolean('responded')->default(true);
            $table->timestamps();

            $table->unique(['scorecard_id', 'candidate_id']);
        });

        Schema::create('elections_scorecard_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('elections_scorecard_responses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('elections_scorecard_items')->cascadeOnDelete();
            // ScorecardAnswer::STANCES.
            $table->string('stance');
            $table->timestamps();

            $table->unique(['response_id', 'item_id']);
        });

        Schema::create('elections_scorecard_takeaways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('elections_scorecard_responses')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('elections_scorecard_categories')->cascadeOnDelete();
            // AI-assisted reading of what this candidate's stances in the
            // category could mean in practice for Salmon Arm.
            $table->text('summary');
            $table->timestamps();

            $table->unique(['response_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_scorecard_takeaways');
        Schema::dropIfExists('elections_scorecard_answers');
        Schema::dropIfExists('elections_scorecard_responses');
        Schema::dropIfExists('elections_scorecard_items');
        Schema::dropIfExists('elections_scorecard_categories');
        Schema::dropIfExists('elections_scorecards');
    }
};
