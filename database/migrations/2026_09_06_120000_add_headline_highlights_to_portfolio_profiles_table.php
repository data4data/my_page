<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            // Which headline words get the accent colours. One flat list, not
            // a translated field: both languages' spellings live together, and
            // only the words in the headline on screen can match.
            $table->json('headline_highlights')->nullable()->after('headline');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            $table->dropColumn('headline_highlights');
        });
    }
};
