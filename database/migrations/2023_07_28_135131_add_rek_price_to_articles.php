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
            $table->double('rek_price_SEK')->default(0)->after('external_cost');
            $table->double('rek_price_EUR')->default(0)->after('rek_price_SEK');
            $table->double('rek_price_DKK')->default(0)->after('rek_price_EUR');
            $table->double('rek_price_NOK')->default(0)->after('rek_price_DKK');

            $table->string('shop_title_sv')->default('')->after('rek_price_NOK');
            $table->string('shop_title_en')->default('')->after('shop_title_sv');
            $table->string('shop_title_da')->default('')->after('shop_title_en');

            $table->longText('shop_description_sv')->nullable()->default(null)->after('shop_title_da');
            $table->longText('shop_description_en')->nullable()->default(null)->after('shop_description_sv');
            $table->longText('shop_description_da')->nullable()->default(null)->after('shop_description_en');

            $table->string('video')->default('')->after('shop_description_da');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('rek_price_sek');
            $table->dropColumn('rek_price_eur');
            $table->dropColumn('rek_price_dkk');
            $table->dropColumn('rek_price_nok');

            $table->dropColumn('shop_title_sv');
            $table->dropColumn('shop_title_en');
            $table->dropColumn('shop_title_da');

            $table->dropColumn('shop_description_sv');
            $table->dropColumn('shop_description_en');
            $table->dropColumn('shop_description_da');

            $table->dropColumn('video');
        });
    }
};
