<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('client_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('developer_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_ngn');
            $table->unsignedBigInteger('fee_ngn')->default(0);
            $table->unsignedBigInteger('net_ngn');
            $table->string('paystack_reference')->nullable()->index();
            $table->string('status')->default('pending');
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['developer_team_id', 'occurred_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_transactions');
    }
};
