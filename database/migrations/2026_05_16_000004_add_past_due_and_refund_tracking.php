<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nolbase_managed_servers', function (Blueprint $table) {
            // Stamped the first time a server flips into past_due. The
            // SuspendPastDueManagedServersJob compares (now - past_due_since)
            // against the grace window. Cleared when the team pays.
            $table->timestamp('past_due_since')->nullable()->after('suspended_at');
            $table->index('past_due_since');
        });

        Schema::table('nolbase_managed_invoices', function (Blueprint $table) {
            // Tracks pro-rata refunds issued when a managed server is deleted
            // mid-month. Used by RefundManagedServerProRata to prevent
            // double-refunding the same invoice.
            $table->unsignedInteger('refunded_ngn')->nullable()->after('billed_at');
            $table->timestamp('refunded_at')->nullable()->after('refunded_ngn');
        });
    }

    public function down(): void
    {
        Schema::table('nolbase_managed_servers', function (Blueprint $table) {
            $table->dropIndex(['past_due_since']);
            $table->dropColumn('past_due_since');
        });

        Schema::table('nolbase_managed_invoices', function (Blueprint $table) {
            $table->dropColumn(['refunded_ngn', 'refunded_at']);
        });
    }
};
