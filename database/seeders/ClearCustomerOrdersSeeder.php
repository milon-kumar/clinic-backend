<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearCustomerOrdersSeeder extends Seeder
{
    /**
     * Remove every customer appointment, treatment order, and purchase.
     * Leaves users, clinics, and the treatment catalog in place.
     *
     * php artisan db:seed --class=ClearCustomerOrdersSeeder
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('appointments')) {
            DB::table('appointments')->update(['next_appointment_id' => null]);
        }

        if (Schema::hasTable('prepaid_packages')) {
            DB::table('prepaid_packages')->update(['next_appointment_id' => null]);
        }

        if (Schema::hasTable('slot_holds')) {
            DB::table('slot_holds')->update(['appointment_id' => null]);
        }

        if (Schema::hasTable('stock_movements')) {
            DB::table('stock_movements')->update(['order_id' => null]);
        }

        $tables = [
            'reviews',
            'slot_holds',
            'appointments',
            'prepaid_packages',
            'invoices',
            'order_lines',
            'orders',
            'payment_sessions',
            'cart_lines',
            'carts',
            'app_notifications',
        ];

        $cleared = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $cleared[$table] = DB::table($table)->count();
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();

        foreach ($cleared as $table => $count) {
            $this->command?->info("Cleared {$count} row(s) from {$table}.");
        }

        $this->command?->info('All customer appointments, treatment orders, and purchases were removed.');
    }
}
