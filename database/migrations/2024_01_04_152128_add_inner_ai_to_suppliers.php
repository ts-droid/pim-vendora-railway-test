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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->integer('purchase_inner_box')->default(0)->after('purchase_master_box');
            $table->integer('purchase_ai')->default(0)->after('purchase_inner_box');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('purchase_inner_box');
            $table->dropColumn('purchase_ai');
        });
    }
};
