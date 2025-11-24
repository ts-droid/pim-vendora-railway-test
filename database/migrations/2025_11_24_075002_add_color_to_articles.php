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
            $table->string('color_sv')->nullable()->default(null);
            $table->string('color_lt')->nullable()->default(null);
            $table->string('color_lv')->nullable()->default(null);
            $table->string('color_et')->nullable()->default(null);
            $table->string('color_is')->nullable()->default(null);
            $table->string('color_fi')->nullable()->default(null);
            $table->string('color_no')->nullable()->default(null);
            $table->string('color_en')->nullable()->default(null);
            $table->string('color_da')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('color_sv');
            $table->dropColumn('color_lt');
            $table->dropColumn('color_lv');
            $table->dropColumn('color_et');
            $table->dropColumn('color_is');
            $table->dropColumn('color_fi');
            $table->dropColumn('color_no');
            $table->dropColumn('color_en');
            $table->dropColumn('color_da');
        });
    }
};
