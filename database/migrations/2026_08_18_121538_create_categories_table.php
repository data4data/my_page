<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // Null = a shared global category, non-null = that user's own.
            //
            // Exactly one level of nesting, enforced in PHP only: a CHECK
            // constraint cannot see another row, so the Form Requests stop a
            // grandchild being written.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#2f75a8');
            $table->string('icon')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
