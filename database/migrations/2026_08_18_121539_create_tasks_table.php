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
            // A plain string, not a DB enum: a new case would need an ALTER
            // TABLE. Validated against TaskStatus and cast on the model.
            $table->string('status')->default('planned');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('result_notes')->nullable();
            $table->string('source')->default('manual');
            $table->string('external_ref')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_datetime']);

            // An overlapping calendar sync or a retry cannot import one remote
            // event twice. Manual tasks hold a NULL external_ref, and a unique
            // index does not compare NULLs.
            $table->unique(['user_id', 'source', 'external_ref'], 'tasks_one_row_per_remote_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
