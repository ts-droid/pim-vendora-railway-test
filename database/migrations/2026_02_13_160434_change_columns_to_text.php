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
            $table->text('status')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->text('ean')->nullable()->change();
            $table->text('ean_inner_box')->nullable()->change();
            $table->text('ean_master_box')->nullable()->change();
            $table->text('wright_article_number')->nullable()->change();
            $table->text('hs_code')->nullable()->change();
            $table->text('origin_country')->nullable()->change();
            $table->text('brand')->nullable()->change();
            $table->text('webshop_created_at')->nullable()->change();
            $table->text('article_type')->nullable()->change();
            $table->text('eu_import_marking')->nullable()->change();
            $table->text('gtin_inner_box')->nullable()->change();
            $table->text('gtin_master_box')->nullable()->change();
            $table->text('gtin_pallet')->nullable()->change();
            $table->text('google_product_category')->nullable()->change();
            $table->text('unspsc_categories')->nullable()->change();
            $table->text('recommended_replacement_article')->nullable()->change();
            $table->text('un_code')->nullable()->change();
            $table->text('minimum_order_quantity')->nullable()->change();
            $table->text('package_image_front')->nullable()->change();
            $table->text('package_image_back')->nullable()->change();
            $table->text('package_image_front_url')->nullable()->change();
            $table->text('package_image_back_url')->nullable()->change();
            $table->text('last_purchase_date')->nullable()->change();
            $table->text('classification')->nullable()->change();
            $table->text('classification_volume')->nullable()->change();
            $table->text('predecessor')->nullable()->change();
            $table->text('upc_code')->nullable()->change();
            $table->text('outlet_mode')->nullable()->change();
            $table->text('outlet_price_mode')->nullable()->change();

            $languages = ['sv', 'lt', 'lv', 'et', 'is', 'fi', 'no', 'en', 'da'];
            $languageColumns = [
                'shop_title',
                'meta_title',
                'reseller_url'
            ];

            foreach ($languageColumns as $column) {
                foreach ($languages as $language) {
                    $table->text($column . '_' . $language)->nullable()->change();
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
