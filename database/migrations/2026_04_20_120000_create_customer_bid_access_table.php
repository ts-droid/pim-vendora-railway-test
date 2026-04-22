<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundspecifik BID-behörighet: whitelist över artiklar där en given
 * kund får se + använda BID-varianter.
 *
 * Utan en rad för (customer, article) = kunden ser inte BID för den
 * artikeln även om articles.bid_enabled=true. Med en rad = full
 * tillgång till alla variant-rader på artikeln.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_bid_access', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->index();
            $table->string('article_number')->index();
            $table->timestamps();
            $table->unique(['customer_number', 'article_number'], 'customer_bid_access_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_bid_access');
    }
};
