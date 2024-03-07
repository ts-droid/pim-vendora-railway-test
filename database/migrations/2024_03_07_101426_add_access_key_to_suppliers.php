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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('access_key')->nullable()->default(null);
        });

        $suppliers = \App\Models\Supplier::all();

        foreach ($suppliers as $supplier) {
            $supplier->update([
                'access_key' => \Illuminate\Support\Str::random(32)
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('access_key');
        });
    }
};
