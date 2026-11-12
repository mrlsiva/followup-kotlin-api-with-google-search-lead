<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business Finder categories, managed by admins (Admin → Categories).
 * Pre-filled with the categories that used to be hard-coded. business_leads.category and
 * business_searches.category keep storing the category NAME, so existing data stays valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();            // stored on business_leads.category
            $table->string('name_ta', 80)->nullable();       // Tamil label
            $table->string('search_term', 80)->nullable();   // words used in Google links (default: name)
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->string('system', 10)->nullable();        // 'all' | 'other' (built-in, cannot be renamed or removed)
            $table->timestamps();
        });

        $rows = [
            ['Hospitals', 'மருத்துவமனைகள்', null],
            ['Clinics', 'மருத்துவமனைகள் (கிளினிக்)', null],
            ['Schools', 'பள்ளிகள்', null],
            ['Colleges', 'கல்லூரிகள்', null],
            ['Manufacturers', 'உற்பத்தியாளர்கள்', null],
            ['Traders', 'வியாபாரிகள்', null],
            ['Distributors', 'விநியோகஸ்தர்கள்', null],
            ['Textile companies', 'ஜவுளி நிறுவனங்கள்', null],
            ['Construction companies', 'கட்டுமான நிறுவனங்கள்', null],
            ['Real estate', 'ரியல் எஸ்டேட்', 'Real estate companies'],
            ['Automobile dealers', 'வாகன விற்பனையாளர்கள்', null],
            ['Restaurants', 'உணவகங்கள்', null],
            ['Hotels', 'ஹோட்டல்கள்', null],
            ['Interior designers', 'உள்அலங்கார வடிவமைப்பாளர்கள்', null],
            ['IT companies', 'IT நிறுவனங்கள்', null],
        ];
        $now = now();
        $insert = [];
        foreach ($rows as $i => [$name, $ta, $term]) {
            $insert[] = ['name' => $name, 'name_ta' => $ta, 'search_term' => $term, 'sort_order' => ($i + 1) * 10,
                'is_active' => true, 'system' => null, 'created_at' => $now, 'updated_at' => $now];
        }
        $insert[] = ['name' => 'Other businesses', 'name_ta' => 'பிற வணிகங்கள்', 'search_term' => 'Businesses', 'sort_order' => 900,
            'is_active' => true, 'system' => 'other', 'created_at' => $now, 'updated_at' => $now];
        $insert[] = ['name' => 'All businesses', 'name_ta' => 'அனைத்து வணிகங்கள்', 'search_term' => 'Businesses', 'sort_order' => 1000,
            'is_active' => true, 'system' => 'all', 'created_at' => $now, 'updated_at' => $now];
        DB::table('business_categories')->insert($insert);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_categories');
    }
};
