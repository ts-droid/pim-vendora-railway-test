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
        Schema::table('article_images', function (Blueprint $table) {
            $table->string('alt_text_sv')->nullable()->after('list_order');
            $table->string('alt_text_en')->nullable()->after('alt_text_sv');
            $table->string('alt_text_da')->nullable()->after('alt_text_en');
            $table->string('alt_text_no')->nullable()->after('alt_text_da');
            $table->string('alt_text_fi')->nullable()->after('alt_text_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_images', function (Blueprint $table) {
            $table->dropColumn('alt_text_sv');
            $table->dropColumn('alt_text_en');
            $table->dropColumn('alt_text_da');
            $table->dropColumn('alt_text_no');
            $table->dropColumn('alt_text_fi');
        });
    }
};
