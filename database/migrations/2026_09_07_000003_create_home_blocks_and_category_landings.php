<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 40);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('kicker')->nullable();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('copy')->nullable();
            $table->string('image')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta2_label')->nullable();
            $table->string('cta2_url')->nullable();
            $table->string('layout', 40)->nullable();
            $table->json('items')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['slot', 'sort_order']);
        });

        Schema::create('category_landings', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('list_title')->nullable();
            $table->string('list_copy')->nullable();
            $table->json('benefits')->nullable();
            $table->json('aliases')->nullable();
            $table->string('cta_title')->nullable();
            $table->text('cta_copy')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_landings');
        Schema::dropIfExists('home_blocks');
    }
};
