<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nolbase_managed_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('billing_month'); // first day of the month being billed
            $table->unsignedInteger('total_ngn');
            $table->json('line_items'); // [{server_id, plan_slug, price_ngn}, ...]
            $table->string('paystack_reference')->nullable()->index();
            $table->string('status')->default('pending'); // pending | success | failed
            $table->text('failure_reason')->nullable();
            $table->timestamp('billed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'billing_month']);
            $table->index(['status', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nolbase_managed_invoices');
    }
};
