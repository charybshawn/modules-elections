<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections_bill_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('elections_candidates')->cascadeOnDelete();
            // Same key the votes carry ("43rd1st:Bill 7"), so a note belongs to one bill for one member.
            $table->string('bill_key');
            $table->text('purpose')->nullable();        // what the bill is for
            $table->text('vote_analysis')->nullable();  // why the member voted as they did, and why it passed or failed
            $table->string('basis')->nullable();        // spoke | caucus | record: what the "why" rests on
            $table->json('sources')->nullable();        // [{label, url}] the synopses were written from
            $table->timestamps();

            $table->unique(['candidate_id', 'bill_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_bill_notes');
    }
};
