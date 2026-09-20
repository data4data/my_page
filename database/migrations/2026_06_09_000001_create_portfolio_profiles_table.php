<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_profiles', function (Blueprint $table) {
            $table->id();
            // The seeder's idempotency key — what updateOrCreate() matches
            // on, and the only thing that identifies a profile. There is one
            // row: nothing in the app can create a second.
            $table->string('slug')->unique();
            // A placeholder. This project is meant to be forked, so nothing
            // seeded names a particular person.
            $table->string('initials', 12)->default('AB');
            $table->json('role');
            $table->json('headline');
            // A flat [{text, tone}] list naming which words in the headline
            // take an accent colour. Deliberately untranslated: both
            // languages' spellings share one list.
            $table->json('headline_highlights')->nullable();
            $table->json('summary');
            $table->json('primary_cta_label')->nullable();
            $table->string('primary_cta_url')->nullable();
            $table->json('secondary_cta_label')->nullable();
            $table->string('secondary_cta_url')->nullable();
            // Flat and untranslated. Both seed null: the contact button hides
            // itself rather than mailing nowhere, and the large link-preview
            // card renders as a blank slab without a picture.
            $table->string('contact_email', 190)->nullable();
            $table->string('social_image_url')->nullable();
            $table->json('location_note')->nullable();
            $table->json('availability_note')->nullable();
            $table->json('quote')->nullable();
            $table->json('quote_author')->nullable();
            // Which language owns the bare URL, and whether the EN/NL switch
            // renders at all. See "One URL per language" in CLAUDE.md.
            $table->string('default_language', 2)->default('en');
            $table->boolean('show_language_toggle')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_profiles');
    }
};
