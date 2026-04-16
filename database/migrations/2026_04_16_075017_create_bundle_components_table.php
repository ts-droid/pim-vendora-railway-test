<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds bundle component tracking. A bundle article (article_type = 'Bundle')
 * aggregates one or more standard articles with quantities. Cost and GTIN
 * are derived from the bundle + its components.
 *
 * The article_type column already exists (migration 2024_07_29_161533) as a
 * free-form string defaulting to 'FinishedGoodItem'. No schema change needed —
 * bundles use article_type = 'Bundle'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_components', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_article_number')->index();
            $table->string('component_article_number')->index();
            $table->integer('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bundle_article_number', 'component_article_number'], 'bundle_components_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
    }
};
