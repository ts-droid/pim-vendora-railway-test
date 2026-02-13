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
            $table->text('status')->nullable()->default(null)->change();
            $table->text('description')->nullable()->default(null)->change();
            $table->text('ean')->nullable()->default(null)->change();
            $table->text('ean_inner_box')->nullable()->default(null)->change();
            $table->text('ean_master_box')->nullable()->default(null)->change();
            $table->text('wright_article_number')->nullable()->default(null)->change();
            $table->text('hs_code')->nullable()->default(null)->change();
            $table->text('origin_country')->nullable()->default(null)->change();
            $table->text('brand')->nullable()->default(null)->change();
            $table->text('webshop_created_at')->nullable()->default(null)->change();
            $table->text('article_type')->nullable()->default(null)->change();
            $table->text('eu_import_marking')->nullable()->default(null)->change();
            $table->text('gtin_inner_box')->nullable()->default(null)->change();
            $table->text('gtin_master_box')->nullable()->default(null)->change();
            $table->text('gtin_pallet')->nullable()->default(null)->change();
            $table->text('google_product_category')->nullable()->default(null)->change();
            $table->text('unspsc_categories')->nullable()->default(null)->change();
            $table->text('recommended_replacement_article')->nullable()->default(null)->change();
            $table->text('un_code')->nullable()->default(null)->change();
            $table->text('minimum_order_quantity')->nullable()->default(null)->change();
            $table->text('package_image_front')->nullable()->default(null)->change();
            $table->text('package_image_back')->nullable()->default(null)->change();
            $table->text('package_image_front_url')->nullable()->default(null)->change();
            $table->text('package_image_back_url')->nullable()->default(null)->change();
            $table->text('last_purchase_date')->nullable()->default(null)->change();
            $table->text('classification')->nullable()->default(null)->change();
            $table->text('classification_volume')->nullable()->default(null)->change();
            $table->text('predecessor')->nullable()->default(null)->change();
            $table->text('upc_code')->nullable()->default(null)->change();
            $table->text('outlet_mode')->nullable()->default(null)->change();
            $table->text('outlet_price_mode')->nullable()->default(null)->change();

            $languages = ['sv', 'lt', 'lv', 'et', 'is', 'fi', 'no', 'en', 'da'];
            $languageColumns = [
                'shop_title',
                'meta_title',
                'reseller_url'
            ];

            foreach ($languageColumns as $column) {
                foreach ($languages as $language) {
                    $table->text($column . '_' . $language)->nullable()->default(null)->change();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            //
        });
    }
};
