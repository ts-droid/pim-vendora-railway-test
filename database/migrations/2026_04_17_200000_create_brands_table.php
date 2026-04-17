<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Nullable so a brand row can exist without overriding the
            // global default margin. Article cascade:
            //   article.standard_reseller_margin (if set, row-level override)
            //   → brands.standard_reseller_margin (brand default)
            //   → global fallback
            $table->decimal('standard_reseller_margin', 5, 2)->nullable();
            $table->decimal('minimum_margin', 5, 2)->nullable();
            $table->timestamps();
        });

        // Backfill one row per distinct articles.brand so the brand-list
        // has something to show immediately. Margins stay NULL — the user
        // can set them per brand from the admin UI.
        $distinctBrands = DB::table('articles')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->pluck('brand');

        $now = now();
        $rows = $distinctBrands->map(fn ($name) => [
            'name' => $name,
            'standard_reseller_margin' => null,
            'minimum_margin' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (!empty($rows)) {
            DB::table('brands')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
