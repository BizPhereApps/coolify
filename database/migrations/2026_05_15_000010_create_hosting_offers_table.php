<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('ram_mb');
            $table->unsignedInteger('disk_gb');
            $table->unsignedInteger('max_apps')->default(1);
            $table->unsignedInteger('max_databases')->default(0);
            $table->boolean('allow_custom_domain')->default(true);
            $table->unsignedInteger('price_ngn_monthly');
            $table->unsignedInteger('price_ngn_annual')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_offers');
    }
};
