<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'clinic_id')) {
                $table->foreignId('clinic_id')->nullable()->after('role')->constrained()->nullOnDelete();
            }
        });

        if (! Schema::hasTable('clinic_staff')) {
            Schema::create('clinic_staff', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('job_title')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['clinic_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('staff_schedules')) {
            Schema::create('staff_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->timestamps();
            });
        }

        DB::table('users')->where('email', 'admin@elixir.com')->update(['role' => 'superadmin']);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('clinic_staff');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'clinic_id')) {
                $table->dropConstrainedForeignId('clinic_id');
            }
        });
    }
};
