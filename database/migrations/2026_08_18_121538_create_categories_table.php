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
            // Nullable: null = a shared/global default category (seeded),
            // non-null = created by that user. One column carrying two kinds
            // of row, which is why CategoryPolicy needs its own rule that a
            // global category is editable by anyone but deletable only while
            // nobody else's subcategory hangs off it.
            //
            // Exactly one level of nesting: a subcategory's parent_id points
            // at a top-level category and is never itself a subcategory.
            // Enforced in PHP only — a CHECK constraint cannot see another
            // row, so the table will hold a grandchild if something writes
            // one. StoreCategoryRequest and UpdateCategoryRequest are what
            // stop it.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#2f75a8');
            $table->string('icon')->nullable();
            $table->timestamps();

            // CategoryController::index() filters on both.
            $table->index(['user_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
