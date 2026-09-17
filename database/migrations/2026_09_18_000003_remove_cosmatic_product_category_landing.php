<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('category_landings')->where('slug', 'cosmatic-product')->delete();
    }

    public function down(): void
    {
        // Legacy landing removed from seed data; no restore.
    }
};
