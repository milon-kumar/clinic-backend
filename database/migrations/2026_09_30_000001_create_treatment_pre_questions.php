<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_pre_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->string('answer_type', 20)->default('text');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('treatment_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepaid_package_id')->unique()->constrained('prepaid_packages')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 80)->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('treatment_intake_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_intake_id')->constrained()->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->string('answer_type', 20);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('answer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_intake_answers');
        Schema::dropIfExists('treatment_intakes');
        Schema::dropIfExists('service_pre_questions');
    }
};
