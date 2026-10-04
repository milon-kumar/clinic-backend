<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_landings', function (Blueprint $table) {
            $table->json('faqs')->nullable()->after('benefits');
        });
    }

    public function down(): void
    {
        Schema::table('category_landings', function (Blueprint $table) {
            $table->dropColumn('faqs');
        });
    }
};
