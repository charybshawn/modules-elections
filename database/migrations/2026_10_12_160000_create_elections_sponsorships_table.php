<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections_sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();
            $table->string('kind');                  // bill | motion
            $table->string('reference');             // "Bill M246", "Motion M 205"
            $table->string('session');               // "43rd Parliament, 2nd session"
            $table->string('title');
            $table->text('summary')->nullable();
            $table->date('introduced_on')->nullable();
            $table->string('status');                // Sponsorship::STATUSES
            $table->string('status_note')->nullable();
            $table->string('context')->nullable();   // how common this is, as one line
            $table->string('source_url');
            $table->string('source_name')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'kind', 'session', 'reference'], 'elections_sponsorships_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_sponsorships');
    }
};
