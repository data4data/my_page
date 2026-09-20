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
            // Denormalised from tasks.user_id: the unique index below is a
            // rule about the user, and task_id cannot be indexed for it.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            // Generated, so it cannot disagree with its own timestamps by any
            // path — a model hook was bypassed by builder update(). STORED, so
            // report totals stay one SUM(); GREATEST floors a backwards pair.
            $table->unsignedInteger('duration_minutes')
                ->storedAs('GREATEST(TIMESTAMPDIFF(MINUTE, started_at, ended_at), 0)')
                ->nullable();

            // "One running timer per user", held by the database as well as by
            // TimerService's lock. NULL once closed, and a unique index does not
            // compare NULLs, so finished logs coexist. VIRTUAL, because MySQL
            // refuses ON DELETE CASCADE on a column a STORED one is built from.
            $table->unsignedBigInteger('running_user_id')
                ->virtualAs('IF(ended_at IS NULL, user_id, NULL)')
                ->nullable();
            $table->unique('running_user_id', 'time_logs_one_running_per_user');

            $table->timestamps();

            // Every timer start asks whereNull('ended_at') for one task.
            $table->index(['task_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
