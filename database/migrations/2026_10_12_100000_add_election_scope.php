<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The module used to hold one election (Salmon Arm municipal 2026), implied
 * rather than stored. The BC provincial election lives alongside it, so
 * candidates, articles and events now belong to an election. Everything on
 * file is backfilled to the municipal one. Entries, planks, pulse mentions
 * and scorecard responses inherit their election through their candidate.
 */
return new class extends Migration
{
    private const SCOPED = ['elections_candidates', 'elections_articles', 'elections_events'];

    public function up(): void
    {
        Schema::create('elections_elections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kind');
            $table->string('riding')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('elections_elections')->insert([
            ['slug' => 'salmon-arm-2026', 'name' => 'Salmon Arm Election 2026', 'kind' => 'municipal', 'riding' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'bc-provincial-salmon-arm-shuswap', 'name' => 'BC Provincial Election', 'kind' => 'provincial', 'riding' => 'Salmon Arm-Shuswap', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $municipalId = DB::table('elections_elections')->where('slug', 'salmon-arm-2026')->value('id');

        foreach (self::SCOPED as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('election_id')->nullable()->constrained('elections_elections');
            });
            DB::table($table)->update(['election_id' => $municipalId]);
        }

        // What was unique across the whole module is now unique within an election.
        Schema::table('elections_candidates', function (Blueprint $t) {
            $t->dropUnique(['name']);
            $t->unique(['election_id', 'name']);
        });
        Schema::table('elections_articles', function (Blueprint $t) {
            $t->dropUnique(['url_hash']);
            $t->unique(['election_id', 'url_hash']);
        });
        Schema::table('elections_events', function (Blueprint $t) {
            $t->dropUnique(['title', 'starts_at']);
            $t->unique(['election_id', 'title', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('elections_events', function (Blueprint $t) {
            $t->dropUnique(['election_id', 'title', 'starts_at']);
            $t->unique(['title', 'starts_at']);
        });
        Schema::table('elections_articles', function (Blueprint $t) {
            $t->dropUnique(['election_id', 'url_hash']);
            $t->unique('url_hash');
        });
        Schema::table('elections_candidates', function (Blueprint $t) {
            $t->dropUnique(['election_id', 'name']);
            $t->unique('name');
        });

        foreach (self::SCOPED as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('election_id');
            });
        }

        Schema::dropIfExists('elections_elections');
    }
};
