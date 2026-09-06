<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            // 45 chars covers an IPv6 literal. Nullable because a console or
            // test-driven attempt has no client address.
            $table->string('ip_address', 45)->nullable();
            // The address that was typed, which for a failure is not a user
            // that exists. Kept so a pattern of guesses is readable.
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            // The two questions this table answers: "what happened lately"
            // and "how often has this address tried".
            $table->index('created_at');
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
