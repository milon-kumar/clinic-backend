<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Weekends (Sunday = 0, Saturday = 6) temporarily closed: remove their schedule rows.
        DB::table('clinic_schedules')->whereIn('day_of_week', [0, 6])->delete();

        DB::table('clinic_schedules')->update([
            'open_time' => '10:00:00',
            'close_time' => '19:00:00',
            'slot_interval_minutes' => 30,
        ]);
    }

    public function down(): void
    {
        // Irreversible data change.
    }
};
