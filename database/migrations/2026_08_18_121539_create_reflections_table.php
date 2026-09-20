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
            // Derived, so the two columns cannot disagree about one fact. A
            // column rather than a computed read, because the index below uses it.
            $table->date('period_end')
                ->storedAs("IF(period_type = 'week', DATE_ADD(period_start, INTERVAL 6 DAY), LAST_DAY(period_start))");
            $table->text('notes')->nullable();
            $table->timestamps();

            // One reflection per user and period.
            $table->unique(['user_id', 'period_type', 'period_start', 'period_end'], 'reflections_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflections');
    }
};
