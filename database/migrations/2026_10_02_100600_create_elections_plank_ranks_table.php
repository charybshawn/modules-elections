<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every tier/rank a plank has had, so the Platform tab can show a
        // candidate's emphasis shifting as the campaign goes on. A row is
        // written when a plank is created and whenever an import changes its
        // tier or rank (Plank's saved hook).
        Schema::create('elections_plank_ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plank_id')->constrained('elections_planks')->cascadeOnDelete();
            $table->string('tier');
            $table->unsignedSmallInteger('rank');
            $table->timestamp('recorded_at');

            $table->index(['plank_id', 'recorded_at']);
        });

        // Planks already on file start their history at their current place.
        DB::table('elections_planks')->orderBy('id')->each(function ($plank) {
            DB::table('elections_plank_ranks')->insert([
                'plank_id' => $plank->id,
                'tier' => $plank->tier,
                'rank' => $plank->rank,
                'recorded_at' => $plank->updated_at ?? $plank->created_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_plank_ranks');
    }
};
