<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A controlled vocabulary of subject tags, library-style: each tag
        // sits under one broad heading (Entry::TOPICS) -- "Social services ›
        // Homelessness" -- and is applied to planks, statements, articles,
        // pulse issues and scorecard statements by the research skills.
        Schema::create('elections_tags', function (Blueprint $table) {
            $table->id();
            // Stable across environments; the import's match key and the URL.
            $table->string('slug')->unique();
            // Entry::TOPICS key -- the heading the tag files under.
            $table->string('topic');
            $table->string('name');
            // What belongs under it, so tagging stays consistent.
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('topic');
        });

        Schema::create('elections_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('elections_tags')->cascadeOnDelete();
            // Morph aliases from ElectionsServiceProvider (elections_plank, ...).
            $table->morphs('taggable');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_taggables');
        Schema::dropIfExists('elections_tags');
    }
};
