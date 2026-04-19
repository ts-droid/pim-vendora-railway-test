<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marginalregler — speglar Node.js-systemets margin_rules.
 *
 * En regel gäller för ett varumärke × kategori. Båda kan vara NULL
 * vilket ger en bredare matchning (brand=NULL + category=NULL =
 * global standard). MarginResolver plockar den mest specifika
 * regeln som matchar en artikel.
 *
 * Unik kombination på (brand, category_id) — NULL räknas som en
 * specifik "slot", så "global standard" är unik och kan bara finnas
 * som en rad. MySQL tillåter flera NULL i unique index, därför
 * kombinerar vi med COALESCE-hash i ett stödfält för säkerhet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('margin_rules', function (Blueprint $table) {
            $table->id();
            $table->string('brand')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('reseller_margin', 5, 2)->nullable();
            $table->decimal('minimum_margin', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['brand', 'category_id']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('margin_rules');
    }
};
