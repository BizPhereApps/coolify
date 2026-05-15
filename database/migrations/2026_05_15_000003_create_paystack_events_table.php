<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->string('paystack_reference')->nullable()->index();
            $table->string('paystack_event_id')->nullable()->unique();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_events');
    }
};
