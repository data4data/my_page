<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // Denormalised from tasks.user_id. It is what makes the unique
            // index below possible — "one running timer per user" is a rule
            // about the user, and reaching them through task_id cannot be
            // indexed.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            // Generated, not written by the app. It used to be computed in
            // TimeLog::booted()'s saving hook, which a builder update()
            // bypasses — so a mass update silently left it null and every
            // report total was short. Derived here it cannot disagree with
            // the timestamps by any path at all.
            //
            // Still STORED, so report totals stay one plain SUM().
            // GREATEST(..., 0) keeps the old hook's flooring: a backwards
            // pair reads as zero rather than subtracting time from a report.
            $table->unsignedInteger('duration_minutes')
                ->storedAs('GREATEST(TIMESTAMPDIFF(MINUTE, started_at, ended_at), 0)')
                ->nullable();

            // "Only one timer runs at a time" held by the database rather
            // than only by TimerService's lock. NULL once the log is closed,
            // and a unique index does not compare NULLs, so any number of
            // finished logs coexist while a second open one is refused.
            //
            // The lock stays: it turns the race into an orderly pause of the
            // other task, which is the behaviour the app wants. This catches
            // the path that forgets to take it.
            // VIRTUAL, not STORED: MySQL refuses ON DELETE CASCADE on a
            // column a *stored* generated column is built from, and the
            // cascade on user_id is worth more than storing a value that is
            // only ever read through this index anyway. InnoDB indexes a
            // virtual column fine, and enforces the uniqueness the same way.
            $table->unsignedBigInteger('running_user_id')
                ->virtualAs('IF(ended_at IS NULL, user_id, NULL)')
                ->nullable();
            $table->unique('running_user_id', 'time_logs_one_running_per_user');

            $table->timestamps();

            // Every timer start asks "is anything still running for this
            // task": whereNull('ended_at') per task.
            $table->index(['task_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
