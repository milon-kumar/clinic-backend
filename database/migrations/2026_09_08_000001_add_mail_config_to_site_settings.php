<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('mail_enabled')->default(true);
            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->string('mail_encryption', 16)->default('tls');
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_enabled',
                'mail_from_name',
                'mail_from_address',
                'mail_host',
                'mail_port',
                'mail_encryption',
                'mail_username',
                'mail_password',
            ]);
        });
    }
};
