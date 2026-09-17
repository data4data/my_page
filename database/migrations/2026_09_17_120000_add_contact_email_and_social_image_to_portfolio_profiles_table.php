<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            // The contact band's "get in touch" address. Was a mailto: written
            // into PublicPage.vue, which is the one thing about the running app
            // that could not be changed from the workspace.
            $table->string('contact_email', 190)->nullable()->after('secondary_cta_url');

            // The picture a link preview shows. Nullable: with no image the
            // card stays the small `summary` kind, which reads better than a
            // large one with a blank slab where the picture goes.
            $table->string('social_image_url')->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_profiles', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'social_image_url']);
        });
    }
};
