<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('kicker')->nullable()->after('name');
            $table->string('name_accent')->nullable()->after('kicker');
            $table->string('tagline')->nullable()->after('name_accent');
            $table->string('professional_title')->nullable()->after('specialty');
            $table->json('credentials')->nullable()->after('professional_title');
            $table->string('expertise_kicker')->nullable()->after('credentials');
            $table->string('expertise_heading')->nullable()->after('expertise_kicker');
            $table->string('expertise_tags')->nullable()->after('expertise_heading');
            $table->json('expertise')->nullable()->after('expertise_tags');
            $table->json('education')->nullable()->after('expertise');
            $table->json('promises')->nullable()->after('education');
            $table->string('cta_title')->nullable()->after('promises');
            $table->text('cta_copy')->nullable()->after('cta_title');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn([
                'kicker',
                'name_accent',
                'tagline',
                'professional_title',
                'credentials',
                'expertise_kicker',
                'expertise_heading',
                'expertise_tags',
                'expertise',
                'education',
                'promises',
                'cta_title',
                'cta_copy',
            ]);
        });
    }
};
