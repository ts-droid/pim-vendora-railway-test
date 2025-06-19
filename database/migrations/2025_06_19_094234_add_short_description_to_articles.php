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
            $table->text('short_description_en')->nullable()->default(null)->after('shop_description_da');
            $table->text('short_description_sv')->nullable()->default(null)->after('short_description_en');
            $table->text('short_description_da')->nullable()->default(null)->after('short_description_sv');
            $table->text('short_description_no')->nullable()->default(null)->after('short_description_da');
            $table->text('short_description_fi')->nullable()->default(null)->after('short_description_no');
            $table->text('short_description_et')->nullable()->default(null)->after('short_description_fi');
            $table->text('short_description_lv')->nullable()->default(null)->after('short_description_et');
            $table->text('short_description_lt')->nullable()->default(null)->after('short_description_lv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('short_description_en');
            $table->dropColumn('short_description_sv');
            $table->dropColumn('short_description_da');
            $table->dropColumn('short_description_no');
            $table->dropColumn('short_description_fi');
            $table->dropColumn('short_description_et');
            $table->dropColumn('short_description_lb');
            $table->dropColumn('short_description_lt');
        });
    }
};
