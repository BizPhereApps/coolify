<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nolbase_admin_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nolbase_admin_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamp('created_at');

            $table->index(['target_type', 'target_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nolbase_admin_audit');
    }
};
