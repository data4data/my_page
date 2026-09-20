<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One row per save of the public page, holding the complete payload as a
    // snapshot. Soft deletes could not do this: the profile row is updated
    // rather than deleted, so its own fields would have no history.
    public function up(): void
    {
        Schema::create('portfolio_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_profile_id')->constrained()->cascadeOnDelete();
            // Nullable so history survives the author being removed.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            // created_at only: a history row is written once.
            $table->timestamp('created_at')->nullable();

            $table->index(['portfolio_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_revisions');
    }
};
