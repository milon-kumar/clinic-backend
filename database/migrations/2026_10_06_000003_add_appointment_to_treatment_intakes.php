<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_intakes', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_intakes', 'appointment_id')) {
                $table->foreignId('appointment_id')
                    ->nullable()
                    ->unique()
                    ->after('order_id')
                    ->constrained('appointments')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('treatment_intakes', 'prepaid_package_id')) {
            Schema::table('treatment_intakes', function (Blueprint $table) {
                $table->dropForeign(['prepaid_package_id']);
            });

            Schema::table('treatment_intakes', function (Blueprint $table) {
                $table->unsignedBigInteger('prepaid_package_id')->nullable()->change();
                $table->foreign('prepaid_package_id')
                    ->references('id')
                    ->on('prepaid_packages')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('treatment_intakes', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_intakes', 'appointment_id')) {
                $table->dropConstrainedForeignId('appointment_id');
            }
        });
    }
};
