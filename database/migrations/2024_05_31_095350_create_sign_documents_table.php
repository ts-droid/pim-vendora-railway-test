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
        Schema::create('sign_documents', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('draft');
            $table->longText('system')->nullable()->default(null);
            $table->longText('prompt')->nullable()->default(null);
            $table->longText('document')->nullable()->default(null);
            $table->string('filename')->nullable()->default(null);
            $table->text('name')->nullable()->default(null);
            $table->text('recipient_email')->nullable()->default(null);
            $table->text('recipient_name')->nullable()->default(null);
            $table->text('recipient_company')->nullable()->default(null);
            $table->text('recipient_org_nr')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sign_documents');
    }
};
