<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The site's change log: what changed, written for people who don't read code.
        Schema::create('elections_changelog', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                // stable id, so a re-import updates the entry
            $table->dateTime('committed_at');              // when the change went in (its commit), which decides which email announces it
            $table->string('title');
            $table->text('body');
            $table->string('scope')->default('both');       // both | provincial | municipal
            $table->string('page')->default('all');         // which page it belongs to (ChangelogEntry::PAGES)
            $table->unsignedTinyInteger('impact')->default(2); // overall effect on the user experience, 1 (small) to 5 (large)
            $table->string('url', 1000)->nullable();        // where to see it
            $table->string('commits')->nullable();          // the commit(s) behind it, for whoever maintains the log; never shown
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_changelog');
    }
};
