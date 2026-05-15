<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('hosting_offer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();

            $table->index(['parent_team_id', 'terminated_at']);
            $table->unique(['client_user_id', 'parent_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_teams');
    }
};
