<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            // The vocabulary a project's tags are picked from. The project
            // itself still stores the names it chose, in its own `tags` JSON
            // column — see CLAUDE.md on what stays denormalised and why.
            $table->string('name', 60)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
