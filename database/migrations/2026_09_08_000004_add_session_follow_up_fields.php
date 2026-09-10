<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->foreignId('next_appointment_id')->nullable()->after('package_id')
                ->constrained('appointments')->nullOnDelete();
        });

        Schema::table('prepaid_packages', function (Blueprint $table) {
            $table->date('next_appointment_date')->nullable()->after('expires_at');
            $table->string('next_appointment_time', 40)->nullable()->after('next_appointment_date');
            $table->foreignId('next_appointment_id')->nullable()->after('next_appointment_time')
                ->constrained('appointments')->nullOnDelete();
            $table->timestamp('next_notified_at')->nullable()->after('next_appointment_id');
        });
    }

    public function down(): void
    {
        Schema::table('prepaid_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_appointment_id');
            $table->dropColumn(['next_appointment_date', 'next_appointment_time', 'next_notified_at']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_appointment_id');
            $table->dropColumn('completed_at');
        });
    }
};
