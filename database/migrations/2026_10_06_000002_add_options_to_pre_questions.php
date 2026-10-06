<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_pre_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('service_pre_questions', 'options')) {
                $table->json('options')->nullable()->after('answer_type');
            }
        });

        Schema::table('treatment_intake_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_intake_answers', 'options')) {
                $table->json('options')->nullable()->after('answer_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_pre_questions', function (Blueprint $table) {
            if (Schema::hasColumn('service_pre_questions', 'options')) {
                $table->dropColumn('options');
            }
        });

        Schema::table('treatment_intake_answers', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_intake_answers', 'options')) {
                $table->dropColumn('options');
            }
        });
    }
};
