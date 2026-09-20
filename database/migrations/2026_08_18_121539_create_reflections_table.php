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
            // Derived, not stored by the app: a week ends six days after it
            // starts and a month at the end of its month, so two columns
            // holding one fact could disagree. The unique index below uses
            // it, which is why it stays a column rather than being computed
            // on read.
            $table->date('period_end')
                ->storedAs("IF(period_type = 'week', DATE_ADD(period_start, INTERVAL 6 DAY), LAST_DAY(period_start))");
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
