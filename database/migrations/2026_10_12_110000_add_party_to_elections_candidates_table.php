<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections_candidates', function (Blueprint $table) {
            // The party on the ballot (provincial candidates), as Elections BC prints it. Null for
            // municipal candidates, who don't run under one.
            $table->string('party')->nullable()->after('is_incumbent');
        });
    }

    public function down(): void
    {
        Schema::table('elections_candidates', function (Blueprint $table) {
            $table->dropColumn('party');
        });
    }
};
