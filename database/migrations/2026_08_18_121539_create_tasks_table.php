<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->unsignedInteger('planned_duration_minutes')->nullable();
            // A plain string, not a DB enum: adding a status to a DB enum
            // needs an ALTER TABLE. Validated against TaskStatus on the way
            // in and cast to it on the model.
            $table->string('status')->default('planned');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('result_notes')->nullable();
            $table->string('source')->default('manual');
            $table->string('external_ref')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_datetime']);

            // Pulling from a calendar means the same event arriving twice —
            // two syncs overlapping, a retry, a provider resending. This
            // makes a duplicate impossible rather than something the sync
            // code has to remember. Manual tasks are unaffected: their
            // external_ref is NULL, and a unique index does not compare
            // NULLs, so there can be any number of them.
            $table->unique(['user_id', 'source', 'external_ref'], 'tasks_one_row_per_remote_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
