<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When an article was linked to a candidate, so a later import that
        // links an existing story to one more candidate shows as new on that
        // candidate's page. Links made before this column existed stay null
        // and count as already seen.
        Schema::table('elections_article_candidate', function (Blueprint $table) {
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('elections_article_candidate', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
