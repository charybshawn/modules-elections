<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();
            // Entry::KINDS / Entry::TOPICS / Entry::SOURCE_TYPES.
            $table->string('kind');
            $table->string('topic')->default('other');
            // Neutral one-or-two sentence paraphrase.
            $table->text('summary');
            // Verbatim words, when the source has them.
            $table->text('quote')->nullable();
            // For Q&A answers: what was asked, paraphrased, asker unnamed.
            $table->text('question')->nullable();
            // Required -- nothing goes on file without a source.
            $table->text('source_url');
            $table->string('source_type');
            $table->string('source_name')->nullable();
            $table->date('published_on')->nullable();
            // sha1 of the source URL + quote (or summary when there's no
            // quote): the import's "same entry" key, since URLs are too long
            // to index directly.
            $table->char('match_hash', 40);
            $table->timestamps();

            $table->unique(['candidate_id', 'match_hash']);
            $table->index(['candidate_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_entries');
    }
};
