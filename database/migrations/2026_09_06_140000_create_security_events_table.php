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
            // 45 chars fits an IPv6 literal; a console attempt has no address.
            $table->string('ip_address', 45)->nullable();
            // The address that was typed; on a failure it may not exist.
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
