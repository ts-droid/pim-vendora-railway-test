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
            $table->string('article_type')->default('FinishedGoodItem');
            $table->float('standard_reseller_margin')->default(0);
            $table->float('minimum_margin')->default(0);
            $table->string('eu_import_marking')->default('');
            $table->integer('pallet_height')->default(0);
            $table->integer('pallet_weight')->default(0);
            $table->integer('pallet_size')->default(0);
            $table->integer('package_weight_paper')->default(0);
            $table->integer('package_weight_plastic')->default(0);
            $table->integer('package_weight_metal')->default(0);
            $table->integer('package_weight_glass')->default(0);
            $table->string('gtin_inner_box')->default('');
            $table->string('gtin_master_box')->default('');
            $table->string('gtin_pallet')->default('');
            $table->text('shop_short_description_sv')->nullable()->default(null);
            $table->text('shop_short_description_fi')->nullable()->default(null);
            $table->text('shop_short_description_no')->nullable()->default(null);
            $table->text('shop_short_description_en')->nullable()->default(null);
            $table->text('shop_short_description_da')->nullable()->default(null);
            $table->string('google_product_category')->default('');
            $table->string('unspsc_categories')->default('');
            $table->string('recommended_replacement_article')->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('article_type');
            $table->dropColumn('standard_reseller_margin');
            $table->dropColumn('minimum_margin');
            $table->dropColumn('eu_import_marking');
            $table->dropColumn('pallet_height');
            $table->dropColumn('pallet_weight');
            $table->dropColumn('pallet_size');
            $table->dropColumn('package_weight_paper');
            $table->dropColumn('package_weight_plastic');
            $table->dropColumn('package_weight_metal');
            $table->dropColumn('package_weight_glass');
            $table->dropColumn('gtin_inner_box');
            $table->dropColumn('gtin_master_box');
            $table->dropColumn('gtin_pallet');
            $table->dropColumn('shop_short_description_sv');
            $table->dropColumn('shop_short_description_fi');
            $table->dropColumn('shop_short_description_en');
            $table->dropColumn('shop_short_description_no');
            $table->dropColumn('shop_short_description_da');
            $table->dropColumn('google_product_category');
            $table->dropColumn('unspsc_categories');
            $table->dropColumn('recommended_replacement_article');
        });
    }
};
