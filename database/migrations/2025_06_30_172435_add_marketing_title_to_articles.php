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
            $table->text('shop_marketing_description_en')->nullable()->default(null);
            $table->text('shop_marketing_description_sv')->nullable()->default(null);
            $table->text('shop_marketing_description_da')->nullable()->default(null);
            $table->text('shop_marketing_description_no')->nullable()->default(null);
            $table->text('shop_marketing_description_fi')->nullable()->default(null);
            $table->text('shop_marketing_description_et')->nullable()->default(null);
            $table->text('shop_marketing_description_lv')->nullable()->default(null);
            $table->text('shop_marketing_description_lt')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('shop_marketing_description_en');
            $table->dropColumn('shop_marketing_description_sv');
            $table->dropColumn('shop_marketing_description_da');
            $table->dropColumn('shop_marketing_description_no');
            $table->dropColumn('shop_marketing_description_fi');
            $table->dropColumn('shop_marketing_description_et');
            $table->dropColumn('shop_marketing_description_lv');
            $table->dropColumn('shop_marketing_description_lt');
        });
    }
};
