<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('region')->nullable();
            $table->json('address_json')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->string('timezone')->default('Europe/London');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('selected_clinic_id')->nullable()->after('is_verified')->constrained('clinics')->nullOnDelete();
        });

        Schema::create('clinic_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('open_time');
            $table->time('close_time');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(60);
            $table->timestamps();
        });

        Schema::create('clinic_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('treatment_type')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedInteger('base_price_pence')->default(0);
            $table->json('images')->nullable();
            $table->boolean('supports_buy')->default(true);
            $table->boolean('supports_book')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('clinic_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->boolean('buy_enabled')->default(true);
            $table->boolean('book_enabled')->default(true);
            $table->boolean('online_buy_enabled')->default(true);
            $table->unsignedInteger('price_pence')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['clinic_id', 'service_id']);
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('sessions')->default(1);
            $table->unsignedInteger('price_pence')->default(0);
            $table->timestamps();
        });

        Schema::create('service_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('service_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer')->nullable();
            $table->timestamps();
        });

        Schema::create('service_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_service_id')->constrained('services')->cascadeOnDelete();
            $table->string('rule');
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cart_type')->default('buy');
            $table->string('promo_code')->nullable();
            $table->string('guest_token')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_pence')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained();
            $table->string('status')->default('pending');
            $table->unsignedInteger('subtotal_pence')->default(0);
            $table->unsignedInteger('discount_pence')->default(0);
            $table->unsignedInteger('total_pence')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_pence')->default(0);
            $table->timestamps();
        });

        Schema::create('prepaid_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sessions_total')->default(1);
            $table->unsignedSmallInteger('sessions_used')->default(0);
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('package_id')->nullable()->constrained('prepaid_packages')->nullOnDelete();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->date('appointment_date');
            $table->string('appointment_time');
            $table->string('status')->default('pending');
            $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('qr_token')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('slot_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('expires_at');
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('type');
            $table->json('rules_json')->nullable();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('requires_login')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->text('bio')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6);
            $table->string('type')->default('email_verify');
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('payment_sessions');
        Schema::dropIfExists('otps');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('slot_holds');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('prepaid_packages');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_lines');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('service_prerequisites');
        Schema::dropIfExists('service_faqs');
        Schema::dropIfExists('service_benefits');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('clinic_services');
        Schema::dropIfExists('services');
        Schema::dropIfExists('clinic_closures');
        Schema::dropIfExists('clinic_schedules');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_clinic_id');
        });

        Schema::dropIfExists('clinics');
    }
};
