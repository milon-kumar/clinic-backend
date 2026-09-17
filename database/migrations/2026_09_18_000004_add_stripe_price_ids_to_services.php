<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('stripe_buy_price_id')->nullable()->after('appointment_amount_pence');
            $table->string('stripe_appointment_price_id')->nullable()->after('stripe_buy_price_id');
        });

        Schema::table('service_packages', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->after('price_pence');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['stripe_buy_price_id', 'stripe_appointment_price_id']);
        });

        Schema::table('service_packages', function (Blueprint $table) {
            $table->dropColumn('stripe_price_id');
        });
    }
};
