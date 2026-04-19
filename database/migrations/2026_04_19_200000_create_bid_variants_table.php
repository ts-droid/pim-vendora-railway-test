<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BID (Business ID) variants — an article can have many, each a price
 * quote variant used on deals/bids. Base data (description, EAN, brand,
 * cost, supplier) is inherited from the parent article — only the
 * variant-specific fields live here.
 *
 * Structure mirrors the bid_variants table in the legacy Node.js
 * priskalkylator so data shape stays compatible if we port rows over.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('articles', 'bid_enabled')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->boolean('bid_enabled')->default(false)->after('standard_reseller_margin');
            });
        }

        Schema::create('bid_variants', function (Blueprint $table) {
            $table->id();
            $table->string('article_number')->index();
            $table->string('variant_sku')->default('');
            $table->decimal('cost', 12, 4)->default(0);
            $table->decimal('fixed_price', 12, 4)->default(0);
            $table->decimal('min_margin', 5, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // article_number isn't a PK on articles in Laravel Eloquent
            // terms, but it's unique. FK is best-effort — skip if the
            // type/collation on the existing column doesn't match.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_variants');
        if (Schema::hasColumn('articles', 'bid_enabled')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropColumn('bid_enabled');
            });
        }
    }
};
