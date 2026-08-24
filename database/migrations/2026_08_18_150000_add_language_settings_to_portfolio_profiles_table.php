<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            // Which language a first-time visitor sees before they've made
            // their own choice (stored client-side after that). 'show...'
            // controls whether the EN/NL toggle appears at all — off means
            // the public page is locked to default_language only.
            $table->string('default_language', 2)->default('en')->after('social_links');
            $table->boolean('show_language_toggle')->default(true)->after('default_language');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            $table->dropColumn(['default_language', 'show_language_toggle']);
        });
    }
};
