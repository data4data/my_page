<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            // Which words in the headline get the accent colours. These were
            // a regex in PublicPage.vue matching one person's actual copy, so
            // changing the headline through the admin quietly lost the accent
            // and no other headline could ever gain one. One flat list rather
            // than a translated field: the terms differ per language ("impact"
            // reads the same in both, "precision"/"precisie" do not), and the
            // match is against whichever language is on screen.
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
