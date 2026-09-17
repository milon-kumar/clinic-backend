<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('stripe_enabled')->default(true)->after('mail_password');
            $table->string('stripe_publishable_key')->nullable()->after('stripe_enabled');
            $table->text('stripe_secret_key')->nullable()->after('stripe_publishable_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_enabled',
                'stripe_publishable_key',
                'stripe_secret_key',
                'stripe_webhook_secret',
            ]);
        });
    }
};
