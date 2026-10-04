<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger of research files `elections:import-pending` has loaded,
     * keyed by content hash so an edited file is imported again.
     */
    public function up(): void
    {
        Schema::create('elections_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('hash', 64)->unique();
            $table->text('summary');
            $table->json('problems')->nullable();
            $table->boolean('marked_only')->default(false);
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index('filename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections_imports');
    }
};
