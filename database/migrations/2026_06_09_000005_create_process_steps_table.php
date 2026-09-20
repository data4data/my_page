<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_profile_id')->constrained()->cascadeOnDelete();
            $table->string('group')->default('input');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('icon')->default('circle');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['portfolio_profile_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_steps');
    }
};
