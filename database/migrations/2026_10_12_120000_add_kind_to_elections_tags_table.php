<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections_tags', function (Blueprint $table) {
            // What the tag is for: a subject (housing, taxes...) or a type of news
            // story (personal, legislative...). Every existing tag is a subject.
            $table->string('kind')->default('subject')->after('slug');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('elections_tags', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
