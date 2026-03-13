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
        Schema::table('customers', function (Blueprint $table) {
            $table->json('shop_url_alternatives')->nullable()->default(null);
            $table->json('shop_search_url_alternatives')->nullable()->default(null);
            $table->json('logo_path_alternatives')->nullable()->default(null);
            $table->json('logo_url_alternatives')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('shop_url_alternatives');
            $table->dropColumn('shop_search_url_alternatives');
            $table->dropColumn('logo_path_alternatives');
            $table->dropColumn('logo_url_alternatives');
        });
    }
};
