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
            // The seeder's idempotency key. There is one row; nothing can
            // create a second.
            $table->string('slug')->unique();
            // A placeholder: nothing seeded names a particular person.
            $table->string('initials', 12)->default('AB');
            $table->json('role');
            $table->json('headline');
            // [{text, tone}]: which headline words take an accent colour.
            // Untranslated — both languages' spellings share one list.
            $table->json('headline_highlights')->nullable();
            $table->json('summary');
            $table->json('primary_cta_label')->nullable();
            $table->string('primary_cta_url')->nullable();
            $table->json('secondary_cta_label')->nullable();
            $table->string('secondary_cta_url')->nullable();
            // Both seed null: the contact button hides itself rather than
            // mailing nowhere, and a picture-less preview card is a blank slab.
            $table->string('contact_email', 190)->nullable();
            $table->string('social_image_url')->nullable();
            $table->json('footer_note_left')->nullable();
            $table->json('footer_note_right')->nullable();
            $table->json('quote')->nullable();
            $table->json('quote_author')->nullable();
            // Which language owns the bare URL, and whether the switch renders.
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
