<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['category_landings', 'services', 'mother_categories', 'doctors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('seo_title', 160)->nullable();
                $table->string('seo_description', 320)->nullable();
                $table->string('hero_title', 160)->nullable();
                $table->text('hero_description')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['category_landings', 'services', 'mother_categories', 'doctors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['seo_title', 'seo_description', 'hero_title', 'hero_description']);
            });
        }
    }
};
