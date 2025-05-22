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
        Schema::table('article_faq_entries', function (Blueprint $table) {
            $table->string('question_en')->after('article_id');
            $table->string('question_sv')->after('question_en');
            $table->string('question_lt')->after('question_sv');
            $table->string('question_lv')->after('question_lt');
            $table->string('question_et')->after('question_lv');
            $table->string('question_is')->after('question_et');
            $table->string('question_fi')->after('question_is');
            $table->string('question_no')->after('question_fi');
            $table->string('question_da')->after('question_no');

            $table->string('answer_en')->after('question_da');
            $table->string('answer_sv')->after('answer_en');
            $table->string('answer_lt')->after('answer_sv');
            $table->string('answer_lv')->after('answer_lt');
            $table->string('answer_et')->after('answer_lv');
            $table->string('answer_is')->after('answer_et');
            $table->string('answer_fi')->after('answer_is');
            $table->string('answer_no')->after('answer_fi');
            $table->string('answer_da')->after('answer_no');

            $table->dropColumn('question');
            $table->dropColumn('answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_faq_entries', function (Blueprint $table) {
            $table->string('question')->after('article_id');
            $table->string('answer')->after('question');

            $table->dropColumn('question_en');
            $table->dropColumn('question_sv');
            $table->dropColumn('question_lt');
            $table->dropColumn('question_lv');
            $table->dropColumn('question_et');
            $table->dropColumn('question_is');
            $table->dropColumn('question_fi');
            $table->dropColumn('question_no');
            $table->dropColumn('question_da');

            $table->dropColumn('answer_en');
            $table->dropColumn('answer_sv');
            $table->dropColumn('answer_lt');
            $table->dropColumn('answer_lv');
            $table->dropColumn('answer_et');
            $table->dropColumn('answer_is');
            $table->dropColumn('answer_fi');
            $table->dropColumn('answer_no');
            $table->dropColumn('answer_da');
        });
    }
};
