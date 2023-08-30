<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->double('retail_price_SEK')->default(0)->after('rek_price_NOK');
            $table->double('retail_price_EUR')->default(0)->after('retail_price_SEK');
            $table->double('retail_price_DKK')->default(0)->after('retail_price_EUR');
            $table->double('retail_price_NOK')->default(0)->after('retail_price_DKK');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('retail_price_SEK');
            $table->dropColumn('retail_price_EUR');
            $table->dropColumn('retail_price_DKK');
            $table->dropColumn('retail_price_NOK');
        });
    }
};
