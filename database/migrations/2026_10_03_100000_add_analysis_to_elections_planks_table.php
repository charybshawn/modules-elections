<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI analysis of a plank: what it means in plain terms, why it could
        // work, why it might not, and a short conclusion -- the same four parts
        // for every plank, with sources for the facts it rests on
        // (Plank::ANALYSIS_PARTS).
        Schema::table('elections_planks', function (Blueprint $table) {
            // {meaning: [{text, sources: [url]}], works: [...], fails: [...], details: [...]}
            $table->json('analysis')->nullable()->after('plan_details');
            $table->date('analysis_on')->nullable()->after('analysis');
        });
    }

    public function down(): void
    {
        Schema::table('elections_planks', function (Blueprint $table) {
            $table->dropColumn(['analysis', 'analysis_on']);
        });
    }
};
