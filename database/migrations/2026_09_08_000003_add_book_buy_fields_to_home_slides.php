<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->string('offer_note')->nullable()->after('subtitle');
            $table->string('book_url')->nullable()->after('link_url');
            $table->string('buy_url')->nullable()->after('book_url');
        });

        $first = DB::table('home_slides')->orderBy('sort_order')->orderBy('id')->first();
        if ($first && blank($first->title)) {
            DB::table('home_slides')->where('id', $first->id)->update([
                'title' => '50% OFF',
                'subtitle' => 'Laser Hair Removal Treatments',
                'offer_note' => 'when you buy 3 or more',
                'book_url' => '/all-treatment?mode=book',
                'buy_url' => '/all-treatment?mode=buy',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->dropColumn(['offer_note', 'book_url', 'buy_url']);
        });
    }
};
