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
            $table->string('meta_title_sv')->default('')->after('review_links');
            $table->string('meta_title_en')->default('')->after('meta_title_sv');
            $table->string('meta_title_da')->default('')->after('meta_title_en');

            $table->text('meta_description_sv')->nullable()->default(null)->after('meta_title_da');
            $table->text('meta_description_en')->nullable()->default(null)->after('meta_description_sv');
            $table->text('meta_description_da')->nullable()->default(null)->after('meta_description_sv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('meta_title_sv');
            $table->dropColumn('meta_title_en');
            $table->dropColumn('meta_title_da');
            $table->dropColumn('meta_description_sv');
            $table->dropColumn('meta_description_en');
            $table->dropColumn('meta_description_da');
        });
    }
};
