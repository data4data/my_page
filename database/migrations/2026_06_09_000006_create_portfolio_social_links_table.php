<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A table, like every other repeating group on the page.
     *
     * These lived in a `social_links` JSON column on the profile, which meant
     * the is_visible filtering payload() applies to the child collections
     * could not reach them — PortfolioContentService had to filter them
     * separately, on a clone. As rows they are ordinary children.
     */
    public function up(): void
    {
        Schema::create('portfolio_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_profile_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            // Resolved through iconMap in resources/js/shared/icons.js; an
            // unknown key renders nothing.
            $table->string('icon')->default('link');

            // The two placements are independent: a link can sit in the side
            // rail, in the page footer, in both, or in neither.
            $table->boolean('in_rail')->default(true);
            $table->boolean('in_footer')->default(true);

            // Generated, so it cannot become a third answer to the question
            // the two placements already settle. It exists because payload()
            // filters every child collection on is_visible, and a link shown
            // in neither place is exactly one that should not be published.
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
