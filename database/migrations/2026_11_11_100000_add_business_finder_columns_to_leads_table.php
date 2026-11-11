<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: phone digits for duplicate detection + do-not-contact flag.
 * Existing columns and data are untouched; mobile_digits is back-filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('mobile_digits', 15)->nullable()->after('mobile');   // Phone::digits(mobile)
            $table->boolean('do_not_contact')->default(false)->after('priority');
            $table->index(['user_id', 'mobile_digits']);
        });

        DB::table('leads')->whereNotNull('mobile')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('leads')->where('id', $row->id)->update(['mobile_digits' => Phone::digits($row->mobile)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'mobile_digits']);
            $table->dropColumn(['mobile_digits', 'do_not_contact']);
        });
    }
};
