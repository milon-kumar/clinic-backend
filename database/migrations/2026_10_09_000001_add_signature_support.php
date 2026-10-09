<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'requires_signature')) {
                $table->boolean('requires_signature')->default(false)->after('allow_local');
            }
        });

        Schema::table('treatment_intakes', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_intakes', 'signature')) {
                $table->mediumText('signature')->nullable()->after('token');
                $table->string('signed_name', 160)->nullable()->after('signature');
                $table->timestamp('signed_at')->nullable()->after('signed_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_intakes', function (Blueprint $table) {
            $table->dropColumn(['signature', 'signed_name', 'signed_at']);
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('requires_signature');
        });
    }
};
