<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections_candidates', function (Blueprint $table) {
            $table->id();
            // The XML import's match key: one election, so a name is unique.
            $table->string('name')->unique();
            $table->string('slug')->unique();
            // Candidate::OFFICES / Candidate::STATUSES.
            $table->string('office');
            $table->string('status')->default('declared');
            $table->boolean('is_incumbent')->default(false);
            $table->string('occupation')->nullable();
            $table->text('bio')->nullable();
            // Where the bio came from -- every fact on file carries a source.
            $table->text('bio_source_url')->nullable();
            $table->text('website')->nullable();
            $table->text('facebook_url')->nullable();
            $table->text('instagram_url')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            // A link to the candidate's own published photo, never a copy.
            $table->text('photo_url')->nullable();
            // Research notes: uncertainty, disagreeing sources, follow-ups.
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('office');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_candidates');
    }
};
