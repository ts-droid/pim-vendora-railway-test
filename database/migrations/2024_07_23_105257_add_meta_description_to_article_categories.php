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
        Schema::table('article_categories', function (Blueprint $table) {
            $table->text('meta_description_sv')->nullable()->default(null)->after('title_fi');
            $table->text('meta_description_en')->nullable()->default(null)->after('meta_description_sv');
            $table->text('meta_description_da')->nullable()->default(null)->after('meta_description_en');
            $table->text('meta_description_no')->nullable()->default(null)->after('meta_description_da');
            $table->text('meta_description_fi')->nullable()->default(null)->after('meta_description_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_categories', function (Blueprint $table) {
            $table->dropColumn('meta_description_sv');
            $table->dropColumn('meta_description_en');
            $table->dropColumn('meta_description_da');
            $table->dropColumn('meta_description_no');
            $table->dropColumn('meta_description_fi');
        });
    }
};
