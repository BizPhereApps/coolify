<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedInteger('price_ngn_monthly')->default(0);
            $table->unsignedInteger('price_ngn_annual')->nullable();

            $table->string('paystack_plan_code_monthly')->nullable();
            $table->string('paystack_plan_code_annual')->nullable();

            $table->unsignedInteger('max_servers')->default(0);
            $table->unsignedInteger('max_apps')->default(0);
            $table->unsignedInteger('max_databases')->default(0);
            $table->unsignedInteger('max_team_members')->default(1);

            $table->json('features')->nullable();

            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_public', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
