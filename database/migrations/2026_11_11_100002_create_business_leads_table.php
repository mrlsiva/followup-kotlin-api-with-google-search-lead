<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Businesses researched in the Business Lead Finder.
 * This table holds research and verification details only. The sales pipeline stays in the
 * existing `leads` table (lead_id) and its follow-ups, so nothing is duplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_search_id')->nullable()->constrained()->nullOnDelete();

            $table->string('business_name', 150);
            $table->string('category', 40)->default('Other businesses');   // App\Enums\BusinessCategory
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone_digits', 15)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('area', 100)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('state', 50)->default('Tamil Nadu');
            $table->string('source_url', 500)->nullable();                  // where the public contact was found

            $table->string('website_url', 255)->nullable();
            $table->string('website_status', 30)->default('Pending verification');   // App\Enums\WebsiteStatus
            $table->dateTime('website_checked_at')->nullable();
            $table->unsignedSmallInteger('website_http_status')->nullable();
            $table->string('website_check_result', 30)->nullable();
            $table->string('website_check_message', 255)->nullable();

            $table->string('contact_status', 20)->default('Pending');         // App\Enums\ContactStatus
            $table->string('contact_source', 100)->nullable();
            $table->date('contact_verified_at')->nullable();
            $table->boolean('do_not_contact')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'phone_digits']);
            $table->index(['user_id', 'website_status']);
            $table->index(['user_id', 'contact_status']);
            $table->index(['user_id', 'category', 'city']);
            $table->index('business_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_leads');
    }
};
