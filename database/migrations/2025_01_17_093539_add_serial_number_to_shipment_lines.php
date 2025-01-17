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
            $table->text('serial_number')->nullable()->default(null)->after('investigation_sound_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->dropColumn('serial_number');
        });
    }
};
