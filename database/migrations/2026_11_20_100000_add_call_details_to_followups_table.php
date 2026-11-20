<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Call details captured by the Android app (from the phone's call log) and the optional call recording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            $table->string('call_direction', 10)->nullable()->after('call_status');     // App\Enums\CallDirection
            $table->string('phone_number', 32)->nullable()->after('call_direction');    // number actually dialled / received
            $table->dateTime('call_started_at')->nullable()->after('phone_number');
            $table->dateTime('call_ended_at')->nullable()->after('call_started_at');
            $table->unsignedInteger('call_duration')->nullable()->after('call_ended_at'); // seconds talked
            $table->string('recording_path')->nullable()->after('note');                  // on the private disk
            $table->string('recording_mime', 50)->nullable()->after('recording_path');
            $table->unsignedBigInteger('recording_size')->nullable()->after('recording_mime'); // bytes
        });
    }

    public function down(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            $table->dropColumn([
                'call_direction', 'phone_number', 'call_started_at', 'call_ended_at', 'call_duration',
                'recording_path', 'recording_mime', 'recording_size',
            ]);
        });
    }
};
