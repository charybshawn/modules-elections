<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every candidate a party is running across the province, from Elections BC's final list.
        Schema::create('elections_party_slate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('elections_parties')->cascadeOnDelete();
            $table->string('riding');
            $table->string('name');
            $table->string('incumbent_riding')->nullable();   // set when the candidate is a sitting MLA: the seat they hold
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['party_id', 'riding', 'name']);
        });

        Schema::table('elections_parties', function (Blueprint $table) {
            $table->string('slate_source_url', 1000)->nullable();
            $table->date('slate_as_of')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('elections_parties', function (Blueprint $table) {
            $table->dropColumn(['slate_source_url', 'slate_as_of']);
        });
        Schema::dropIfExists('elections_party_slate');
    }
};
