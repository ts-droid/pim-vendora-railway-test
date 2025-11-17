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
        Schema::table('purchase_order_exceptions', function (Blueprint $table) {
            $table->timestamp('handled_at')->nullable()->default(null)->after('images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_exceptions', function (Blueprint $table) {
            $table->dropColumn('handled_at');
        });
    }
};
