<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mother_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(false);
            $table->timestamps();
        });

        Schema::table('category_landings', function (Blueprint $table) {
            $table->foreignId('mother_category_id')
                ->nullable()
                ->after('category')
                ->constrained('mother_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('category_landings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mother_category_id');
        });

        Schema::dropIfExists('mother_categories');
    }
};
