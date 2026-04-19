<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikelspecifikt stöd / kampanjstöd.
 *
 * Schema matchar Node.js-systemets article_supports-tabell, med
 * layer + customer_type så samma rad kan beskriva både
 * leverantörsstöd (supplier-layer) och varumärkesstöd på artikelnivå,
 * för olika kundtyper (upfront / rebate osv).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_supports', function (Blueprint $table) {
            $table->id();
            $table->string('article_number')->index();
            $table->string('layer')->default('supplier');
            $table->string('customer_type')->default('upfront');
            $table->decimal('value', 12, 4)->default(0);
            $table->boolean('is_percentage')->default(false);
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_supports');
    }
};
