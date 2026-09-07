<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'full_name')) {
                $table->string('full_name')->nullable()->after('package_id');
            }
            if (! Schema::hasColumn('appointments', 'phone')) {
                $table->string('phone')->nullable()->after('full_name');
            }
            if (! Schema::hasColumn('appointments', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });

        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('promotions', 'clinic_id')) {
                $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('promotions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (! Schema::hasColumn('promotions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable();
            }
        });

        if (! Schema::hasTable('payment_sessions')) {
            Schema::create('payment_sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('purpose');
                $table->json('payload')->nullable();
                $table->string('status')->default('pending');
                $table->unsignedInteger('amount_pence')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sessions');
    }
};
