<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The column default carried one person's initials, which is the wrong
     * thing for a project meant to be forked and made someone else's.
     *
     * A migration rather than an edit to the original, so an install that has
     * already run that one ends up with the same schema as a fresh clone. No
     * row is touched: every insert supplies its own initials, so this only
     * changes what a fork inherits.
     */
    public function up(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            $table->string('initials', 12)->default('AB')->change();
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            $table->string('initials', 12)->default('OA')->change();
        });
    }
};
