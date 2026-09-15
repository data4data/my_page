<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The default carried one person's initials, which is wrong for a project
     * meant to be forked. A migration rather than an edit to the original, so
     * an existing install ends up with the same schema as a fresh clone.
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
