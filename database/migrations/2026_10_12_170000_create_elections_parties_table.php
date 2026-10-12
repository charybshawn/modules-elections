<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections_parties', function (Blueprint $table) {
            $table->id();
            // The name candidates carry in their `party` field, as Elections BC prints it.
            $table->string('name')->unique();
            $table->string('full_name')->nullable();
            $table->string('leader')->nullable();            // who leads it now
            $table->text('leader_note')->nullable();         // since when, and any interim or recent change
            $table->string('founded')->nullable();           // how long it has existed: the year and lineage
            $table->text('founded_note')->nullable();
            $table->unsignedSmallInteger('times_in_government')->nullable();
            $table->text('government_note')->nullable();     // which governments, when, or "never"
            $table->text('principles')->nullable();          // what it stands for, in its own terms
            $table->json('platform')->nullable();            // the current platform, as short points
            $table->date('as_of');                           // the date this was checked
            $table->json('sources')->nullable();             // [{label, url}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_parties');
    }
};
