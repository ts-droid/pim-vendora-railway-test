<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TranslateExcludeService
{
    public static function getAll(): array
    {
        return DB::table('translate_excludes')
            ->pluck('value')
            ->toArray();
    }

    public static function add(string $value): void
    {
        $value = trim($value);
        if (!$value) return;

        DB::table('translate_excludes')->updateOrInsert(['value' => $value]);
    }

    public static function remove(string $value): void
    {
        $value = trim($value);
        if (!$value) return;

        DB::table('translate_excludes')
            ->where('value', '=', $value)
            ->delete();
    }
}
