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
            $table->integer('sales_30_days')->default(0)->after('is_webshop');
            $table->string('webshop_created_at')->default('')->after('sales_30_days');
            $table->json('review_links')->nullable()->default(null)->after('webshop_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('sales_30_days');
            $table->dropColumn('webshop_created_at');
            $table->dropColumn('review_links');
        });
    }
};
