<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('footer_qr_image')->nullable();
            $table->string('footer_qr_link')->nullable();
            $table->string('footer_qr_label')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['footer_qr_image', 'footer_qr_link', 'footer_qr_label']);
        });
    }
};
