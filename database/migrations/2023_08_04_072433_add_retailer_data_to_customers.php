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
            $table->string('shop_url')->default('')->after('country');
            $table->string('logo_path')->default('')->after('shop_url');
            $table->string('logo_url')->default('')->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('shop_url');
            $table->dropColumn('logo_path');
            $table->dropColumn('logo_url');
        });
    }
};
