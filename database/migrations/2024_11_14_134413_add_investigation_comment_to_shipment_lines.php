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
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->text('investigation_comment')->nullable()->default(null)->after('picked_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->dropColumn('investigation_comment');
        });
    }
};
