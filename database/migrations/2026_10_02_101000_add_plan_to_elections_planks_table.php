<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a candidate has actually said about carrying a plank out --
        // the how, the money, the timing -- or that they haven't said yet.
        Schema::table('elections_planks', function (Blueprint $table) {
            // Plank::PLAN_STATUSES; null = not assessed yet.
            $table->string('plan_status')->nullable()->after('has_commitment');
            // One or two neutral sentences: what they'd actually do.
            $table->text('plan_summary')->nullable()->after('plan_status');
            // [{aspect: Plank::PLAN_ASPECTS, text, source_url}] -- each
            // detail tied to the source it was read in.
            $table->json('plan_details')->nullable()->after('plan_summary');
        });
    }

    public function down(): void
    {
        Schema::table('elections_planks', function (Blueprint $table) {
            $table->dropColumn(['plan_status', 'plan_summary', 'plan_details']);
        });
    }
};
