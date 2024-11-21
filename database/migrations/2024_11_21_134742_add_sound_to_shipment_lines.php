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
            $table->string('investigation_sound_path')->nullable()->default(null)->after('investigation_comment');
            $table->string('investigation_sound_url')->nullable()->default(null)->after('investigation_sound_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->dropColumn('investigation_sound_path');
            $table->dropColumn('investigation_sound_url');
        });
    }
};
