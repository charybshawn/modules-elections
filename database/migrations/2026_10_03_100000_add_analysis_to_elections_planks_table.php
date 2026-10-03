<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI analysis of a plank: its likely impact, what makes it hard to
        // carry out, and how it could fall short -- the same three questions
        // for every plank, with sources for the facts it rests on.
        Schema::table('elections_planks', function (Blueprint $table) {
            // {impact: [{text, sources: [url]}], challenges: [...], risks: [...]}
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
