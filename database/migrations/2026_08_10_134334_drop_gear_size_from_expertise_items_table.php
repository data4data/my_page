<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expertise_items', function (Blueprint $table) {
            $table->dropColumn('gear_size');
        });
    }

    public function down(): void
    {
        Schema::table('expertise_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('gear_size')->default(120);
        });
    }
};
