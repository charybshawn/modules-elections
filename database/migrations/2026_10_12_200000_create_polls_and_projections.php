<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Province-wide voting-intention polls, one row per published poll.
        Schema::create('elections_polls', function (Blueprint $table) {
            $table->id();
            $table->string('pollster');
            $table->date('fielded_from')->nullable();
            $table->date('fielded_to');                     // last day in the field (or the date an aggregator gives)
            $table->date('published_on')->nullable();
            $table->unsignedInteger('sample_size')->nullable();
            $table->string('method')->nullable();           // online panel, IVR, ...
            $table->json('results');                        // {party name: percent of decided voters}
            $table->string('source_url', 1000);
            $table->string('source_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['pollster', 'fielded_to']);
        });

        // Modelled (not polled) vote shares for each riding.
        Schema::create('elections_riding_projections', function (Blueprint $table) {
            $table->id();
            $table->string('riding')->unique();
            $table->string('rating')->nullable();           // safe | likely | leaning | toss up, as the model labels the riding
            $table->string('leader')->nullable();           // the party projected to win
            $table->json('shares');                         // [{party, percent, margin}] largest first
            $table->date('as_of');
            $table->string('source_url', 1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_riding_projections');
        Schema::dropIfExists('elections_polls');
    }
};
