<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nolbase_managed_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('hetzner');
            $table->string('provider_resource_id')->nullable()->index();
            $table->string('plan_slug')->nullable();
            $table->string('location_slug')->nullable();
            $table->unsignedInteger('cost_basis_ngn_monthly')->nullable();
            $table->unsignedInteger('price_ngn_monthly')->nullable();
            $table->unsignedInteger('markup_pct')->default(50);
            $table->string('billing_status')->default('active');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('decommissioned_at')->nullable();
            $table->timestamps();

            $table->index('billing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nolbase_managed_servers');
    }
};
