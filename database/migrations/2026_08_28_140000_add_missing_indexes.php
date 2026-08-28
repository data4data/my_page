<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite does not create an index for a foreign key; MySQL does. Since SQLite
 * is what this project develops against, every FK column was unindexed there.
 *
 * Only composites MySQL's own FK index does not already cover are added, so
 * nothing here is a duplicate on either engine. Each starts with the FK
 * column, so it doubles as the plain prefix index SQLite was missing.
 */
return new class extends Migration
{
    // Portfolio children: always eager-loaded and ordered by sort_order.
    private const ORDERED_CHILDREN = [
        'portfolio_metrics',
        'expertise_items',
        'portfolio_projects',
        'process_steps',
    ];

    public function up(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            // Every timer start asks "is anything still running for this task",
            // i.e. whereNull('ended_at') per task. This is the one that matters.
            $table->index(['task_id', 'ended_at']);
        });

        Schema::table('categories', function (Blueprint $table) {
            // CategoryController::index() filters on both.
            $table->index(['user_id', 'parent_id']);
        });

        foreach (self::ORDERED_CHILDREN as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index(['portfolio_profile_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            $table->dropIndex(['task_id', 'ended_at']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'parent_id']);
        });

        foreach (self::ORDERED_CHILDREN as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['portfolio_profile_id', 'sort_order']);
            });
        }
    }
};
