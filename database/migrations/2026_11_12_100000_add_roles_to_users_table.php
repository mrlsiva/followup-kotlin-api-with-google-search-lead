<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: roles (admin / manager / sales), manager teams, enable/disable, preferred language.
 * The oldest existing user becomes admin so the web panel has an administrator after deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 10)->default('sales')->after('password');      // App\Enums\UserRole
            $table->foreignId('manager_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('manager_id');
            $table->string('locale', 5)->default('en')->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('locale');
            $table->index(['role', 'is_active']);
        });

        if (! DB::table('users')->where('role', 'admin')->exists()) {
            $first = DB::table('users')->orderBy('id')->value('id');
            if ($first) {
                DB::table('users')->where('id', $first)->update(['role' => 'admin']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['role', 'is_active', 'locale', 'last_login_at']);
        });
    }
};
