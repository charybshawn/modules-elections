<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // A party's own page needs an address.
        Schema::table('elections_parties', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });
        foreach (DB::table('elections_parties')->get(['id', 'name']) as $party) {
            DB::table('elections_parties')->where('id', $party->id)->update(['slug' => Str::slug($party->name)]);
        }

        // The fact-check record: what the party says, and what the record shows.
        Schema::create('elections_party_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('elections_parties')->cascadeOnDelete();
            $table->string('key');                          // stable id within the party
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('kind')->default('claim');       // claim | pledge
            $table->text('claim');                          // the party's own words, or a close paraphrase
            $table->string('source_url');                   // where the party says it
            $table->string('source_name')->nullable();
            $table->string('topic')->nullable();
            $table->string('rating');                       // PartyClaim::RATINGS
            $table->text('summary');                        // what the evidence shows
            $table->json('evidence')->nullable();           // [{kind, text, label, url}]
            $table->date('checked_on');
            $table->timestamps();

            $table->unique(['party_id', 'key']);
        });

        // News about the party as a whole (candidate news stays on candidate pages).
        Schema::create('elections_party_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('elections_parties')->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 1000);
            $table->string('url_hash', 64);
            $table->string('outlet')->nullable();
            $table->date('published_on')->nullable();
            $table->text('summary')->nullable();
            $table->string('type')->default('legislative'); // PartyArticle::TYPES
            $table->timestamps();

            $table->unique(['party_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_party_articles');
        Schema::dropIfExists('elections_party_claims');
        Schema::table('elections_parties', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
