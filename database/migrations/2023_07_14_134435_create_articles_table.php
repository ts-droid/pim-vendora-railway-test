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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->default(null);
            $table->string('article_number')->nullable()->default(null);
            $table->string('description')->nullable()->default(null);
            $table->string('ean')->nullable()->default(null);
            $table->string('wright_article_number')->nullable()->default(null);
            $table->string('supplier_number')->nullable()->default(null);
            $table->double('cost_price_avg')->default(0);
            $table->integer('stock')->default(0);
            $table->string('hs_code')->nullable()->default(null);
            $table->string('origin_country')->nullable()->default(null);
            $table->integer('inner_box')->default(0);
            $table->integer('master_box')->default(0);
            $table->float('width')->default(0);
            $table->float('height')->default(0);
            $table->float('depth')->default(0);
            $table->float('master_box_width')->default(0);
            $table->float('master_box_height')->default(0);
            $table->float('master_box_depth')->default(0);
            $table->float('inner_box_width')->default(0);
            $table->float('inner_box_height')->default(0);
            $table->float('inner_box_depth')->default(0);
            $table->float('weight')->default(0);
            $table->float('master_box_weight')->default(0);
            $table->float('inner_box_weight')->default(0);
            $table->string('brand')->nullable()->default(null);
            $table->tinyInteger('is_webshop')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
