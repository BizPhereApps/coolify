<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_team_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('hosting_offer_id')->constrained()->restrictOnDelete();
            $table->string('paystack_subscription_code')->nullable()->unique();
            $table->string('paystack_customer_code')->nullable()->index();
            $table->string('paystack_email_token')->nullable();
            $table->string('status')->default('pending');
            $table->string('period')->default('monthly');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspend_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_subscriptions');
    }
};
