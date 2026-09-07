<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'appointment_amount_pence')) {
                $table->unsignedInteger('appointment_amount_pence')->default(0)->after('base_price_pence');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'amount_pence')) {
                $table->unsignedInteger('amount_pence')->default(0)->after('payment_method');
            }
        });

        if (Schema::hasColumn('services', 'appointment_amount_pence') && Schema::hasColumn('services', 'base_price_pence')) {
            DB::table('services')->update([
                'appointment_amount_pence' => DB::raw('base_price_pence'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'appointment_amount_pence')) {
                $table->dropColumn('appointment_amount_pence');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'amount_pence')) {
                $table->dropColumn('amount_pence');
            }
        });
    }
};
