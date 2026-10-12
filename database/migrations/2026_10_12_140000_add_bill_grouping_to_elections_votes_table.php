<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections_votes', function (Blueprint $table) {
            // So a bill reads as one entry: its stage votes up front, its amendments and
            // committee votes folded under it. bill_key is unique within a session, because
            // bill numbers restart every session ("43rd1st:Bill 7").
            $table->string('bill_key')->nullable()->after('reference');
            $table->string('bill_title')->nullable()->after('bill_key');
            $table->string('stage')->nullable()->after('bill_title'); // Vote::STAGES
            $table->index(['candidate_id', 'bill_key']);
        });
    }

    public function down(): void
    {
        Schema::table('elections_votes', function (Blueprint $table) {
            $table->dropIndex(['candidate_id', 'bill_key']);
            $table->dropColumn(['bill_key', 'bill_title', 'stage']);
        });
    }
};
