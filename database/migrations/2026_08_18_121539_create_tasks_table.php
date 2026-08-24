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
            // Plain string (not a DB-level enum) for portability across
            // MySQL/SQLite — validated against TaskStatus in the controller,
            // cast to it on the model.
            $table->string('status')->default('planned');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('result_notes')->nullable();
            $table->string('source')->default('manual');
            $table->string('external_ref')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
