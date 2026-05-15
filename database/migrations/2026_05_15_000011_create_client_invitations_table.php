<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('developer_team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('email');
            $table->string('project_name');
            $table->string('invitation_token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index(['developer_team_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_invitations');
    }
};
