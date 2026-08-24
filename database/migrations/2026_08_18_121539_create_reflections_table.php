<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_type'); // week | month — see ReflectionPeriodType
            $table->date('period_start');
            $table->date('period_end');
            $table->text('notes')->nullable();
            $table->timestamps();

            // One reflection per user/period — lets the report endpoint
            // upsert by (period_type, period_start, period_end) with no
            // separate lookup, matching the spec's "upsert by period" note.
            $table->unique(['user_id', 'period_type', 'period_start', 'period_end'], 'reflections_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflections');
    }
};
