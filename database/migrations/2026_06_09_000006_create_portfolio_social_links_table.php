<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_profile_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            // Resolved through iconMap; an unknown key renders nothing.
            $table->string('icon')->default('link');

            // Independent: a link can sit in the rail, the footer, both, or neither.
            $table->boolean('in_rail')->default(true);
            $table->boolean('in_footer')->default(true);

            // Generated, so it cannot get out of step with the two placements.
            // payload() filters every child collection on is_visible.
            $table->boolean('is_visible')
                ->storedAs('(in_rail OR in_footer)');

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['portfolio_profile_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_social_links');
    }
};
