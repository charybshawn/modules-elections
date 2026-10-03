<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('url');
            // sha1(url): the import's match key.
            $table->char('url_hash', 40)->unique();
            // "Salmon Arm Observer", "Castanet", ...
            $table->string('outlet')->nullable();
            $table->date('published_on')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index('published_on');
        });

        Schema::create('elections_article_candidate', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('elections_articles')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();

            $table->primary(['article_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_article_candidate');
        Schema::dropIfExists('elections_articles');
    }
};
