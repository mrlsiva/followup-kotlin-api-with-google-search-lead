<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('mobile', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('source', 20)->default('Other');      // App\Enums\LeadSource
            $table->string('status', 20)->default('New');        // App\Enums\LeadStatus
            $table->string('priority', 10)->default('Medium');   // App\Enums\LeadPriority
            $table->text('notes')->nullable();
            $table->dateTime('next_followup_at')->nullable();    // mirrors the lead's pending follow-up
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'next_followup_at']);
            $table->index('name');
            $table->index('mobile');
            $table->index('company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
