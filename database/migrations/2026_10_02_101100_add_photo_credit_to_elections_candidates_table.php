<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who the candidate's photo belongs to (e.g. "Friday AM"), shown
        // under it on the candidate page.
        Schema::table('elections_candidates', function (Blueprint $table) {
            $table->string('photo_credit')->nullable()->after('photo_url');
        });
    }

    public function down(): void
    {
        Schema::table('elections_candidates', function (Blueprint $table) {
            $table->dropColumn('photo_credit');
        });
    }
};
