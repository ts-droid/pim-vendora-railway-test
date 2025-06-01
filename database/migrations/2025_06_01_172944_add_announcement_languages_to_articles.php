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
            $table->text('announcement_en')->nullable()->default(null)->after('announcement');
            $table->text('announcement_sv')->nullable()->default(null)->after('announcement_en');
            $table->text('announcement_da')->nullable()->default(null)->after('announcement_sv');
            $table->text('announcement_no')->nullable()->default(null)->after('announcement_da');
            $table->text('announcement_fi')->nullable()->default(null)->after('announcement_no');
            $table->text('announcement_is')->nullable()->default(null)->after('announcement_fi');
            $table->text('announcement_et')->nullable()->default(null)->after('announcement_is');
            $table->text('announcement_lv')->nullable()->default(null)->after('announcement_et');
            $table->text('announcement_lt')->nullable()->default(null)->after('announcement_lv');

            $table->dropColumn('announcement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('announcement')->nullable()->default(null)->after('announcement_lt');

            $table->dropColumn('announcement_en');
            $table->dropColumn('announcement_sv');
            $table->dropColumn('announcement_da');
            $table->dropColumn('announcement_no');
            $table->dropColumn('announcement_fi');
            $table->dropColumn('announcement_is');
            $table->dropColumn('announcement_et');
            $table->dropColumn('announcement_lv');
            $table->dropColumn('announcement_lt');
        });
    }
};
