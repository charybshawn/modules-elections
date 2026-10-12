<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One recorded vote by one person: a Legislature division, a council
        // or board recorded vote. Every row carries the record it came from.
        Schema::create('elections_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();
            $table->string('body');            // Vote::BODIES: legislature, council, board
            $table->date('voted_on');
            $table->string('title');           // "Bill 7: Economic Stabilization (Tariff Response) Act, third reading"
            $table->string('reference')->nullable(); // "Bill 7", "Motion 12", a division number
            $table->text('description')->nullable(); // the record's own wording of what was decided
            $table->string('vote');            // Vote::VOTES: yea, nay, abstained
            $table->string('outcome')->nullable(); // Vote::OUTCOMES: passed, defeated
            $table->unsignedSmallInteger('tally_for')->nullable();
            $table->unsignedSmallInteger('tally_against')->nullable();
            $table->text('source_url');
            $table->string('source_name')->nullable();
            // The import's "same vote" key: source, date, reference and wording.
            $table->char('match_hash', 40);
            $table->timestamps();

            $table->unique(['candidate_id', 'match_hash']);
            $table->index(['candidate_id', 'voted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_votes');
    }
};
