<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // ElectionEvent::KINDS.
            $table->string('kind')->default('other');
            // Local (Pacific) wall-clock time, as the source states it.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->text('url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // The XML import's match key.
            $table->unique(['title', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_events');
    }
};
