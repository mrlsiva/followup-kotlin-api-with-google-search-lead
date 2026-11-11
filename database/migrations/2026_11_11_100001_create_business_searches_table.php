<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Search history of the Business Lead Finder (one row per generated set of search links). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name', 150)->nullable();
            $table->string('match_type', 10)->default('partial');   // exact | partial
            $table->string('keywords', 150)->nullable();
            $table->string('category', 40)->nullable();             // App\Enums\BusinessCategory
            $table->string('state', 50)->default('Tamil Nadu');
            $table->string('district', 60)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('area', 100)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->unsignedSmallInteger('quantity')->default(20);
            $table->string('website_filter', 30)->nullable();
            $table->string('contact_filter', 30)->nullable();
            $table->string('query', 300);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_searches');
    }
};
