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
            $table->string('slug')->unique();
            $table->string('type')->default('person');
            $table->boolean('is_active')->default(true);
            $table->string('initials', 12)->default('OA');
            $table->json('role');
            $table->json('headline');
            $table->json('summary');
            $table->json('primary_cta_label')->nullable();
            $table->string('primary_cta_url')->nullable();
            $table->json('secondary_cta_label')->nullable();
            $table->string('secondary_cta_url')->nullable();
            $table->json('location_note')->nullable();
            $table->json('availability_note')->nullable();
            $table->json('quote')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_profiles');
    }
};
