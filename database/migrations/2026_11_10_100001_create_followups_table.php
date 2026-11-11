<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status', 12)->default('pending');   // pending | completed
            $table->dateTime('completed_at')->nullable();
            $table->string('call_status', 20)->nullable();      // App\Enums\CallStatus
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'scheduled_at']);
            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followups');
    }
};
