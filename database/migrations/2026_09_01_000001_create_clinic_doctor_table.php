<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinic_doctor')) {
            Schema::create('clinic_doctor', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['clinic_id', 'doctor_id']);
            });
        }

        $now = now();
        $rows = DB::table('doctors')
            ->whereNotNull('clinic_id')
            ->get(['id', 'clinic_id']);

        foreach ($rows as $doctor) {
            $exists = DB::table('clinic_doctor')
                ->where('clinic_id', $doctor->clinic_id)
                ->where('doctor_id', $doctor->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('clinic_doctor')->insert([
                'clinic_id' => $doctor->clinic_id,
                'doctor_id' => $doctor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_doctor');
    }
};
