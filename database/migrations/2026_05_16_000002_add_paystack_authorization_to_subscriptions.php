<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Paystack returns this on transaction verify and on subscription.create
            // webhooks. Stored so we can charge_authorization for managed-server
            // monthly billing (variable amount, can't ride the existing
            // subscription plan because the plan is fixed-price).
            $table->string('paystack_authorization_code')->nullable()->after('paystack_email_token');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('paystack_authorization_code');
        });
    }
};
